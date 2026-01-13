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

class MentionNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $mentionedUser;
    protected Workspace $workspace;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'alice']);
        $this->mentionedUser = User::factory()->create(['name' => 'bob']);
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
            'user_id' => $this->mentionedUser->id,
            'role' => 'member',
        ]);

        // Add users to conversation
        $this->conversation->users()->attach($this->user->id);
        $this->conversation->users()->attach($this->mentionedUser->id);
    }

    public function test_posting_message_with_mention_creates_notification(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/conversations/{$this->conversation->id}/messages", [
            'body_md' => 'Hey @bob, check this out!',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->mentionedUser->id,
            'type' => Notification::TYPE_MENTION,
            'actor_user_id' => $this->user->id,
        ]);
    }

    public function test_posting_message_with_multiple_mentions_creates_multiple_notifications(): void
    {
        Sanctum::actingAs($this->user);

        $user3 = User::factory()->create(['name' => 'charlie']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user3->id,
            'role' => 'member',
        ]);
        $this->conversation->users()->attach($user3->id);

        $response = $this->postJson("/api/conversations/{$this->conversation->id}/messages", [
            'body_md' => 'Hey @bob and @charlie, check this out!',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->mentionedUser->id,
            'type' => Notification::TYPE_MENTION,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user3->id,
            'type' => Notification::TYPE_MENTION,
        ]);
    }

    public function test_self_mention_does_not_create_notification(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/conversations/{$this->conversation->id}/messages", [
            'body_md' => 'Hey @alice, reminding myself!',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_MENTION,
        ]);
    }

    public function test_mention_of_non_existent_user_does_not_create_notification(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/conversations/{$this->conversation->id}/messages", [
            'body_md' => 'Hey @nonexistentuser, are you there?',
        ]);

        $response->assertStatus(201);

        $this->assertEquals(0, Notification::where('type', Notification::TYPE_MENTION)->count());
    }

    public function test_duplicate_mention_in_same_message_creates_one_notification(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/conversations/{$this->conversation->id}/messages", [
            'body_md' => 'Hey @bob, I need to tell you @bob about something!',
        ]);

        $response->assertStatus(201);

        $count = Notification::where('user_id', $this->mentionedUser->id)
            ->where('type', Notification::TYPE_MENTION)
            ->count();

        $this->assertEquals(1, $count);
    }
}
