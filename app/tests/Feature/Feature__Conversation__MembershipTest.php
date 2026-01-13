<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Feature__Conversation__MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_members_to_private_channel(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $conversation = Conversation::factory()->privateChannel()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($admin, 'admin');

        $response = $this->actingAs($admin)
            ->postJson("/api/conversations/{$conversation->id}/members", [
                'user_id' => $member->id,
            ]);

        $response->assertStatus(201);
        $this->assertTrue($conversation->fresh()->isMember($member));
    }

    public function test_member_cannot_add_members_to_private_channel(): void
    {
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $conversation = Conversation::factory()->privateChannel()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($member1, 'member');

        $response = $this->actingAs($member1)
            ->postJson("/api/conversations/{$conversation->id}/members", [
                'user_id' => $member2->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_leave_channel(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($user, 'member');

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/leave");

        $response->assertStatus(200);
        $this->assertFalse($conversation->fresh()->isMember($user));
    }

    public function test_admin_can_remove_member(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($admin, 'admin');
        $conversation->addMember($member, 'member');

        $response = $this->actingAs($admin)
            ->deleteJson("/api/conversations/{$conversation->id}/members/{$member->id}");

        $response->assertStatus(200);
        $this->assertFalse($conversation->fresh()->isMember($member));
    }

    public function test_member_cannot_remove_other_members(): void
    {
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $conversation->addMember($member1, 'member');
        $conversation->addMember($member2, 'member');

        $response = $this->actingAs($member1)
            ->deleteJson("/api/conversations/{$conversation->id}/members/{$member2->id}");

        $response->assertStatus(403);
    }
}
