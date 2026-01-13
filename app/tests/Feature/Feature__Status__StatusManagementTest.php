<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\StatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Feature__Status__StatusManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 'active',
        ]);
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);

        // Add user to workspace
        WorkspaceMember::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
    }

    /** @test */
    public function user_can_get_their_current_status()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'status_message',
                'status_emoji',
                'is_online',
                'is_dnd',
            ]);
    }

    /** @test */
    public function user_can_update_their_status()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/status', [
                'status' => 'away',
                'message' => 'On break',
                'emoji' => '☕',
                'expires_in' => 30,
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertEquals('away', $this->user->status);
        $this->assertEquals('On break', $this->user->status_message);
        $this->assertEquals('☕', $this->user->status_emoji);
        $this->assertNotNull($this->user->status_expires_at);
    }

    /** @test */
    public function user_can_set_dnd_status()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/status', [
                'status' => 'dnd',
                'message' => 'In a meeting',
                'emoji' => '📅',
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertEquals('dnd', $this->user->status);
        $this->assertTrue($this->user->is_dnd);
    }

    /** @test */
    public function user_can_clear_their_status()
    {
        // First set a status
        $this->user->update([
            'status' => 'away',
            'status_message' => 'Away',
            'status_emoji' => '💤',
        ]);

        // Then clear it
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/status');

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertEquals('active', $this->user->status);
        $this->assertNull($this->user->status_message);
        $this->assertNull($this->user->status_emoji);
    }

    /** @test */
    public function user_can_get_status_presets()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/status/presets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'presets' => [
                    '*' => [
                        'status',
                        'message',
                        'emoji',
                        'label',
                    ]
                ]
            ]);

        $presets = $response->json('presets');
        $this->assertGreaterThan(0, count($presets));
    }

    /** @test */
    public function status_requires_valid_status_value()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/status', [
                'status' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function status_message_cannot_exceed_max_length()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/status', [
                'status' => 'active',
                'message' => str_repeat('a', 101), // Max is 100
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    /** @test */
    public function expired_status_is_automatically_cleared()
    {
        $statusService = app(StatusService::class);

        // Set status that expires in the past
        $this->user->update([
            'status' => 'away',
            'status_message' => 'On break',
            'status_expires_at' => now()->subMinutes(5),
        ]);

        $effectiveStatus = $statusService->getEffectiveStatus($this->user);

        $this->assertEquals('active', $effectiveStatus['status']);
        $this->assertNull($effectiveStatus['status_message']);
    }

    /** @test */
    public function user_can_enable_dnd_mode()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/status/dnd', [
                'duration' => 60, // 1 hour
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertTrue($this->user->is_dnd);
        $this->assertNotNull($this->user->dnd_until);
    }

    /** @test */
    public function user_can_disable_dnd_mode()
    {
        // First enable DND
        $this->user->update([
            'is_dnd' => true,
            'dnd_until' => now()->addHours(2),
        ]);

        // Then disable it
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/status/dnd');

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertFalse($this->user->is_dnd);
        $this->assertNull($this->user->dnd_until);
    }

    /** @test */
    public function expired_dnd_mode_is_automatically_disabled()
    {
        $statusService = app(StatusService::class);

        $this->user->update([
            'is_dnd' => true,
            'dnd_until' => now()->subMinutes(5),
        ]);

        $shouldReceive = $statusService->shouldReceiveNotification($this->user);

        $this->assertTrue($shouldReceive);
        $this->user->refresh();
        $this->assertFalse($this->user->is_dnd);
    }

    /** @test */
    public function user_in_dnd_mode_should_not_receive_notifications()
    {
        $statusService = app(StatusService::class);

        $this->user->update([
            'is_dnd' => true,
            'dnd_until' => now()->addHours(1),
        ]);

        $shouldReceive = $statusService->shouldReceiveNotification($this->user);

        $this->assertFalse($shouldReceive);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_status_endpoints()
    {
        $this->getJson('/api/status')
            ->assertStatus(401);

        $this->putJson('/api/status', ['status' => 'away'])
            ->assertStatus(401);

        $this->deleteJson('/api/status')
            ->assertStatus(401);

        $this->getJson('/api/status/presets')
            ->assertStatus(401);
    }

    /** @test */
    public function status_changes_are_properly_persisted()
    {
        $this->actingAs($this->user)
            ->putJson('/api/status', [
                'status' => 'away',
                'message' => 'Lunch break',
                'emoji' => '🍽️',
                'expires_in' => 60,
            ]);

        // Refresh from database
        $this->user->refresh();

        $this->assertEquals('away', $this->user->status);
        $this->assertEquals('Lunch break', $this->user->status_message);
        $this->assertEquals('🍽️', $this->user->status_emoji);
        $this->assertInstanceOf(\Carbon\Carbon::class, $this->user->status_expires_at);
    }

    /** @test */
    public function clearing_status_sets_all_fields_to_defaults()
    {
        // Set complex status
        $this->user->update([
            'status' => 'dnd',
            'status_message' => 'Focus time',
            'status_emoji' => '🎯',
            'status_expires_at' => now()->addHours(2),
            'is_dnd' => true,
            'dnd_until' => now()->addHours(2),
        ]);

        // Clear status
        $this->actingAs($this->user)
            ->deleteJson('/api/status');

        $this->user->refresh();

        $this->assertEquals('active', $this->user->status);
        $this->assertNull($this->user->status_message);
        $this->assertNull($this->user->status_emoji);
        $this->assertNull($this->user->status_expires_at);
    }
}
