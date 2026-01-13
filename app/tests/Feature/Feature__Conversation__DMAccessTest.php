<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Feature__Conversation__DMAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_dm_with_another_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($user1)
            ->postJson('/api/conversations', [
                'workspace_id' => $workspace->id,
                'type' => 'dm',
                'member_ids' => [$user2->id],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('conversations', [
            'workspace_id' => $workspace->id,
            'type' => 'dm',
        ]);
    }

    public function test_duplicate_dm_returns_existing_conversation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $response1 = $this->actingAs($user1)
            ->postJson('/api/conversations', [
                'workspace_id' => $workspace->id,
                'type' => 'dm',
                'member_ids' => [$user2->id],
            ]);

        $conversationId = $response1->json('id');

        $response2 = $this->actingAs($user1)
            ->postJson('/api/conversations', [
                'workspace_id' => $workspace->id,
                'type' => 'dm',
                'member_ids' => [$user2->id],
            ]);

        $response2->assertStatus(200);
        $this->assertEquals($conversationId, $response2->json('id'));
    }

    public function test_user_can_create_group_dm(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($user1)
            ->postJson('/api/conversations', [
                'workspace_id' => $workspace->id,
                'type' => 'group_dm',
                'member_ids' => [$user2->id, $user3->id],
            ]);

        $response->assertStatus(201);
        $conversation = Conversation::find($response->json('id'));
        $this->assertCount(3, $conversation->members);
    }

    public function test_dm_name_is_auto_generated(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($user1)
            ->postJson('/api/conversations', [
                'workspace_id' => $workspace->id,
                'type' => 'dm',
                'member_ids' => [$user2->id],
            ]);

        $response->assertStatus(201);
        $conversation = Conversation::find($response->json('id'));
        $this->assertNull($conversation->name);
    }

    public function test_only_participants_can_view_dm(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $conversation = Conversation::factory()->directMessage()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($user1);
        $conversation->addMember($user2);

        $response = $this->actingAs($outsider)
            ->getJson("/api/conversations/{$conversation->id}");

        $response->assertStatus(403);
    }

    public function test_non_participant_cannot_view_dm(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $conversation = Conversation::factory()->directMessage()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($user1);
        $conversation->addMember($user2);

        $response = $this->actingAs($outsider)
            ->getJson("/api/conversations/{$conversation->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_leave_group_dm(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $conversation = Conversation::factory()->groupDM()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($user1);
        $conversation->addMember($user2);

        $response = $this->actingAs($user1)
            ->postJson("/api/conversations/{$conversation->id}/leave");

        $response->assertStatus(200);
        $this->assertFalse($conversation->fresh()->isMember($user1));
    }
}
