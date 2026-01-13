<?php

namespace Tests\Unit\Unit;

use Tests\TestCase;
use App\Services\NotificationService;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Events\NotificationCreatedEvent;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationService;
    protected User $user;
    protected User $mentionedUser;
    protected Workspace $workspace;
    protected Conversation $conversation;
    protected Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationService = app(NotificationService::class);
        $this->user = User::factory()->create();
        $this->mentionedUser = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->conversation = Conversation::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'body_md' => 'Hello @mentioned_user',
        ]);
    }

    public function test_create_mention_notification_creates_notification(): void
    {
        $notification = $this->notificationService->createMentionNotification(
            $this->message,
            $this->mentionedUser
        );

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $this->mentionedUser->id,
            'type' => Notification::TYPE_MENTION,
            'actor_user_id' => $this->user->id,
            'message_id' => $this->message->id,
            'conversation_id' => $this->conversation->id,
            'workspace_id' => $this->workspace->id,
            'is_read' => false,
        ]);
    }

    public function test_create_mention_notification_prevents_self_notification(): void
    {
        $notification = $this->notificationService->createMentionNotification(
            $this->message,
            $this->user // Same as message author
        );

        $this->assertNull($notification);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_MENTION,
            'message_id' => $this->message->id,
        ]);
    }

    public function test_create_mention_notification_prevents_duplicates(): void
    {
        // Create first notification
        $notification1 = $this->notificationService->createMentionNotification(
            $this->message,
            $this->mentionedUser
        );

        // Attempt to create duplicate
        $notification2 = $this->notificationService->createMentionNotification(
            $this->message,
            $this->mentionedUser
        );

        $this->assertEquals($notification1->id, $notification2->id);
        $this->assertEquals(1, Notification::where('user_id', $this->mentionedUser->id)
            ->where('message_id', $this->message->id)
            ->where('type', Notification::TYPE_MENTION)
            ->count());
    }

    public function test_create_mention_notification_broadcasts_event(): void
    {
        Event::fake();

        $this->notificationService->createMentionNotification(
            $this->message,
            $this->mentionedUser
        );

        Event::assertDispatched(NotificationCreatedEvent::class, function ($event) {
            return $event->notification->user_id === $this->mentionedUser->id
                && $event->notification->type === Notification::TYPE_MENTION;
        });
    }

    public function test_create_thread_reply_notification_creates_notification(): void
    {
        $parentMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->mentionedUser->id,
        ]);

        $replyMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'parent_message_id' => $parentMessage->id,
        ]);

        $notification = $this->notificationService->createThreadReplyNotification(
            $replyMessage,
            $parentMessage
        );

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $this->mentionedUser->id,
            'type' => Notification::TYPE_THREAD_REPLY,
            'actor_user_id' => $this->user->id,
            'message_id' => $replyMessage->id,
        ]);
    }

    public function test_create_thread_reply_notification_prevents_self_notification(): void
    {
        $parentMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
        ]);

        $replyMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id, // Same author
            'parent_message_id' => $parentMessage->id,
        ]);

        $notification = $this->notificationService->createThreadReplyNotification(
            $replyMessage,
            $parentMessage
        );

        $this->assertNull($notification);
    }

    public function test_mark_as_read_updates_notification(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        $result = $this->notificationService->markAsRead($notification);

        $this->assertTrue($result);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_mark_all_as_read_updates_all_user_notifications(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        // Create another user's notification (should not be affected)
        Notification::factory()->create([
            'user_id' => $this->mentionedUser->id,
            'is_read' => false,
        ]);

        $count = $this->notificationService->markAllAsRead($this->user);

        $this->assertEquals(3, $count);
        $this->assertEquals(0, Notification::where('user_id', $this->user->id)
            ->where('is_read', false)
            ->count());
        $this->assertEquals(1, Notification::where('user_id', $this->mentionedUser->id)
            ->where('is_read', false)
            ->count());
    }

    public function test_get_unread_count_returns_correct_count(): void
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_read' => true,
        ]);

        $count = $this->notificationService->getUnreadCount($this->user);

        $this->assertEquals(5, $count);
    }

    public function test_get_unread_for_digest_returns_unread_notifications(): void
    {
        $unread1 = Notification::factory()->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        $unread2 = Notification::factory()->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        Notification::factory()->create([
            'user_id' => $this->user->id,
            'is_read' => true,
        ]);

        $notifications = $this->notificationService->getUnreadForDigest($this->user);

        $this->assertCount(2, $notifications);
        $this->assertTrue($notifications->contains($unread1));
        $this->assertTrue($notifications->contains($unread2));
    }
}
