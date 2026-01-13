<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Reaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Feature__Message__BasicCRUDTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_message_in_conversation(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'public_channel',
        ]);
        $conversation->addMember($user, 'owner');

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'body_md' => 'Hello, **world**!',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'body_md', 'body_html', 'user']);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body_md' => 'Hello, **world**!',
        ]);
    }

    public function test_non_member_cannot_create_message(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $otherUser->id]);
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'private_channel',
        ]);
        $conversation->addMember($otherUser, 'owner');

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'body_md' => 'Hello!',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_list_messages(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($user, 'owner');

        Message::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/conversations/{$conversation->id}/messages");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'messages' => [
                '*' => ['id', 'body_md', 'body_html', 'user'],
            ],
            'has_more',
        ]);
        $response->assertJsonCount(3, 'messages');
    }

    public function test_user_can_update_own_message_within_15_minutes(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($user, 'owner');

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body_md' => 'Original text',
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/messages/{$message->id}", [
                'body_md' => 'Updated text',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'body_md' => 'Updated text',
        ]);
        $message->refresh();
        $this->assertNotNull($message->edited_at);
    }

    public function test_user_can_delete_own_message(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($user, 'owner');

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/messages/{$message->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_user_can_add_reaction_to_message(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($user, 'owner');

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/reactions", [
                'emoji' => '👍',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);
    }

    public function test_user_can_remove_reaction(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($user, 'owner');

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        $reaction = Reaction::factory()->create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '❤️',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/messages/{$message->id}/reactions/❤️");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reactions', [
            'id' => $reaction->id,
        ]);
    }
}
