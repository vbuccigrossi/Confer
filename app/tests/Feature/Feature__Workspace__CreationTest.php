<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Feature__Workspace__CreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_workspace(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/workspaces', [
                'name' => 'My Workspace',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'slug',
                'owner_id',
                'created_at',
                'updated_at',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'name' => 'My Workspace',
            'slug' => 'my-workspace',
            'owner_id' => $user->id,
        ]);

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $response->json('id'),
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_workspace_slug_must_be_unique(): void
    {
        $user = User::factory()->create();
        Workspace::factory()->create(['slug' => 'test-workspace']);

        $response = $this->actingAs($user)
            ->postJson('/api/workspaces', [
                'name' => 'Test Workspace',
                'slug' => 'test-workspace',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_user_can_list_their_workspaces(): void
    {
        $user = User::factory()->create();
        $workspace1 = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace1->members()->create(['user_id' => $user->id, 'role' => 'owner']);

        $workspace2 = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace2->members()->create(['user_id' => $user->id, 'role' => 'owner']);

        $response = $this->actingAs($user)
            ->getJson('/api/workspaces');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_user_can_update_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->create(['user_id' => $user->id, 'role' => 'owner']);

        $response = $this->actingAs($user)
            ->putJson("/api/workspaces/{$workspace->id}", [
                'name' => 'Updated Workspace',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Updated Workspace',
        ]);
    }

    public function test_only_owner_can_delete_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->create(['user_id' => $owner->id, 'role' => 'owner']);
        $workspace->members()->create(['user_id' => $member->id, 'role' => 'member']);

        // Member cannot delete
        $response = $this->actingAs($member)
            ->deleteJson("/api/workspaces/{$workspace->id}");
        $response->assertStatus(403);

        // Owner can delete
        $response = $this->actingAs($owner)
            ->deleteJson("/api/workspaces/{$workspace->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('workspaces', ['id' => $workspace->id]);
    }
}
