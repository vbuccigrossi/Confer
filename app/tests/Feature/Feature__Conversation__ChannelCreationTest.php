<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Feature__Conversation__ChannelCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_public_channel(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/conversations', [
                'workspace_id' => $workspace->id,
                'type' => 'public_channel',
                'name' => 'General',
                'topic' => 'General discussion',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('conversations', [
            'workspace_id' => $workspace->id,
            'type' => 'public_channel',
            'name' => 'General',
        ]);
    }

    public function test_user_can_create_private_channel(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/conversations', [
                'workspace_id' => $workspace->id,
                'type' => 'private_channel',
                'name' => 'Secret',
                'member_ids' => [$member->id],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('conversations', [
            'type' => 'private_channel',
            'name' => 'Secret',
        ]);
    }

    public function test_channel_name_is_required(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/conversations', [
                'workspace_id' => $workspace->id,
                'type' => 'public_channel',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_channel_slug_must_be_unique_per_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        
        Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'slug' => 'test-channel',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/conversations', [
                'workspace_id' => $workspace->id,
                'type' => 'public_channel',
                'name' => 'Test',
                'slug' => 'test-channel',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_user_can_list_joined_channels(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'public_channel',
        ]);
        $conversation->addMember($user);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations?workspace_id=' . $workspace->id);

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_user_can_discover_public_channels(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        
        Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'public_channel',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations/discover?workspace_id=' . $workspace->id);

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_private_channels_not_in_discovery(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        
        Conversation::factory()->privateChannel()->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations/discover?workspace_id=' . $workspace->id);

        $response->assertStatus(200)
            ->assertJsonCount(0);
    }

    public function test_owner_can_archive_channel(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $conversation->addMember($user, 'owner');

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/archive");

        $response->assertStatus(200);
        $this->assertTrue($conversation->fresh()->is_archived);
    }

    public function test_member_cannot_archive_channel(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);
        $conversation->addMember($owner, 'owner');
        $conversation->addMember($member, 'member');

        $response = $this->actingAs($member)
            ->postJson("/api/conversations/{$conversation->id}/archive");

        $response->assertStatus(403);
    }
}
