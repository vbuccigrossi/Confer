<?php

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Feature__Workspace__InviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_admin_can_send_invite(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->create(['user_id' => $owner->id, 'role' => 'owner']);

        $response = $this->actingAs($owner)
            ->postJson("/api/workspaces/{$workspace->id}/invites", [
                'email' => 'invitee@example.com',
                'role' => 'member',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'workspace_id',
                'email',
                'token',
                'role',
                'expires_at',
            ]);

        $this->assertDatabaseHas('invites', [
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
        ]);
    }

    public function test_invite_has_unique_token(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->create(['user_id' => $owner->id, 'role' => 'owner']);

        $invite1 = Invite::factory()->create(['workspace_id' => $workspace->id]);
        $invite2 = Invite::factory()->create(['workspace_id' => $workspace->id]);

        $this->assertNotEquals($invite1->token, $invite2->token);
    }

    public function test_user_can_accept_valid_invite(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->create(['user_id' => $owner->id, 'role' => 'owner']);

        $invite = Invite::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($invitee)
            ->postJson('/api/invites/accept', [
                'token' => $invite->token,
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Invite accepted successfully']);

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $invitee->id,
            'role' => 'member',
        ]);

        $this->assertDatabaseHas('invites', [
            'id' => $invite->id,
        ]);

        $invite->refresh();
        $this->assertNotNull($invite->accepted_at);
    }

    public function test_user_cannot_accept_expired_invite(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        
        $invite = Invite::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'expires_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($invitee)
            ->postJson('/api/invites/accept', [
                'token' => $invite->token,
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invite expired']);
    }

    public function test_invite_cannot_be_accepted_twice(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->create(['user_id' => $owner->id, 'role' => 'owner']);

        $invite = Invite::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($invitee)
            ->postJson('/api/invites/accept', [
                'token' => $invite->token,
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invite already accepted']);
    }

    public function test_invite_email_must_match_user(): void
    {
        $owner = User::factory()->create();
        $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);
        
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        
        $invite = Invite::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'correct@example.com',
        ]);

        $response = $this->actingAs($wrongUser)
            ->postJson('/api/invites/accept', [
                'token' => $invite->token,
            ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Invite email does not match authenticated user']);
    }

    public function test_owner_can_revoke_invite(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->create(['user_id' => $owner->id, 'role' => 'owner']);

        $invite = Invite::factory()->create([
            'workspace_id' => $workspace->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->deleteJson("/api/workspaces/{$workspace->id}/invites/{$invite->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Invite revoked successfully']);

        $this->assertDatabaseMissing('invites', ['id' => $invite->id]);
    }
}
