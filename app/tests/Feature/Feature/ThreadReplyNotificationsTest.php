<?php

namespace Tests\Feature\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use Laravel\Sanctum\Sanctum;

class ThreadReplyNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Workspace $workspace;
    protected Conversation $conversation;
    protected Message $parentMessage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->conversation = Conversation::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        // Add users to workspace
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => 'member',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);

        // Add users to conversation
        $this->conversation->users()->attach($this->user->id);
        $this->conversation->users()->attach($this->otherUser->id);

        // Create parent message
        $this->parentMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'body_md' => 'Original message',
        ]);
    }

    public function test_replying_to_thread_creates_notification(): void
    {
        Sanctum::actingAs($this->otherUser);

        $response = $this->postJson("/api/conversations/{$this->conversation->id}/messages", [
            'body_md' => 'This is a reply',
            'parent_message_id' => $this->parentMessage->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_THREAD_REPLY,
            'actor_user_id' => $this->otherUser->id,
        ]);
    }

    public function test_replying_to_own_thread_does_not_create_notification(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/conversations/{$this->conversation->id}/messages", [
            'body_md' => 'Replying to myself',
            'parent_message_id' => $this->parentMessage->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_THREAD_REPLY,
        ]);
    }

    public function test_thread_reply_with_mention_creates_both_notifications(): void
    {
        $thirdUser = User::factory()->create(['name' => 'charlie']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $thirdUser->id,
            'role' => 'member',
        ]);
        $this->conversation->users()->attach($thirdUser->id);

        Sanctum::actingAs($this->otherUser);

        $response = $this->postJson("/api/conversations/{$this->conversation->id}/messages", [
            'body_md' => 'Reply with @charlie mentioned',
            'parent_message_id' => $this->parentMessage->id,
        ]);

        $response->assertStatus(201);

        // Thread reply notification for parent author
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_THREAD_REPLY,
        ]);

        // Mention notification for mentioned user
        $this->assertDatabaseHas('notifications', [
            'user_id' => $thirdUser->id,
            'type' => Notification::TYPE_MENTION,
        ]);
    }
}
