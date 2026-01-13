<?php

namespace Tests\Feature\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Notification;
use Laravel\Sanctum\Sanctum;

class NotificationsAPITest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_list_notifications_requires_authentication(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }

    public function test_list_notifications_returns_user_notifications(): void
    {
        Sanctum::actingAs($this->user);

        $notification1 = Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $notification2 = Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Create another user's notification (should not be returned)
        $otherUser = User::factory()->create();
        Notification::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'notifications' => [
                '*' => [
                    'id',
                    'type',
                    'is_read',
                    'created_at',
                ],
            ],
            'unread_count',
        ]);

        $this->assertCount(2, $response->json('notifications'));
    }

    public function test_list_notifications_filters_unread(): void
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_read' => true,
        ]);

        $response = $this->getJson('/api/notifications?only_unread=1');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('notifications'));
    }

    public function test_list_notifications_respects_limit(): void
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(10)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/notifications?limit=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('notifications'));
    }

    public function test_mark_notification_as_read_requires_authentication(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(401);
    }

    public function test_mark_notification_as_read_updates_status(): void
    {
        Sanctum::actingAs($this->user);

        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        $response = $this->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_mark_notification_as_read_prevents_cross_user_access(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $otherUser->id,
            'is_read' => false,
        ]);

        $response = $this->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(403);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => false,
        ]);
    }

    public function test_mark_all_as_read_requires_authentication(): void
    {
        $response = $this->postJson('/api/notifications/read-all');

        $response->assertStatus(401);
    }

    public function test_mark_all_as_read_updates_all_user_notifications(): void
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        $otherUser = User::factory()->create();
        Notification::factory()->create([
            'user_id' => $otherUser->id,
            'is_read' => false,
        ]);

        $response = $this->postJson('/api/notifications/read-all');

        $response->assertStatus(200);
        $response->assertJson([
            'count' => 5,
        ]);

        // Verify user's notifications are marked read
        $this->assertEquals(0, Notification::where('user_id', $this->user->id)
            ->where('is_read', false)
            ->count());

        // Verify other user's notification remains unread
        $this->assertEquals(1, Notification::where('user_id', $otherUser->id)
            ->where('is_read', false)
            ->count());
    }
}
