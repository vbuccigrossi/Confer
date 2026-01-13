<?php

namespace Tests\Unit\Unit;

use Tests\TestCase;
use App\Services\PresenceService;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Event;
use App\Events\PresenceUserOnlineEvent;
use App\Events\PresenceUserOfflineEvent;

class PresenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PresenceService $presenceService;
    protected User $user;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presenceService = app(PresenceService::class);
        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();

        // Clear Redis before each test
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_mark_online_sets_redis_key_with_ttl(): void
    {
        $this->presenceService->markOnline($this->user, $this->workspace);

        $key = "presence:user:{$this->user->id}";
        $data = Redis::get($key);

        $this->assertNotNull($data);

        $decoded = json_decode($data, true);
        $this->assertEquals($this->workspace->id, $decoded['workspace_id']);
        $this->assertArrayHasKey('last_seen', $decoded);

        // Check TTL is set
        $ttl = Redis::ttl($key);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(60, $ttl);
    }

    public function test_mark_online_adds_user_to_workspace_set(): void
    {
        $this->presenceService->markOnline($this->user, $this->workspace);

        $workspaceKey = "presence:workspace:{$this->workspace->id}";
        $members = Redis::smembers($workspaceKey);

        $this->assertContains((string)$this->user->id, $members);
    }

    public function test_mark_online_broadcasts_event(): void
    {
        Event::fake();

        $this->presenceService->markOnline($this->user, $this->workspace);

        Event::assertDispatched(PresenceUserOnlineEvent::class, function ($event) {
            return $event->user->id === $this->user->id
                && $event->workspace->id === $this->workspace->id;
        });
    }

    public function test_mark_offline_removes_redis_key(): void
    {
        // First mark online
        $this->presenceService->markOnline($this->user, $this->workspace);

        // Then mark offline
        $this->presenceService->markOffline($this->user);

        $key = "presence:user:{$this->user->id}";
        $data = Redis::get($key);

        $this->assertNull($data);
    }

    public function test_mark_offline_broadcasts_event(): void
    {
        Event::fake();

        $this->presenceService->markOnline($this->user, $this->workspace);
        $this->presenceService->markOffline($this->user);

        Event::assertDispatched(PresenceUserOfflineEvent::class, function ($event) {
            return $event->user->id === $this->user->id
                && $event->workspace->id === $this->workspace->id;
        });
    }

    public function test_is_online_returns_true_when_user_online(): void
    {
        $this->presenceService->markOnline($this->user, $this->workspace);

        $this->assertTrue($this->presenceService->isOnline($this->user));
    }

    public function test_is_online_returns_false_when_user_offline(): void
    {
        $this->assertFalse($this->presenceService->isOnline($this->user));
    }

    public function test_get_online_users_returns_online_users(): void
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $this->presenceService->markOnline($this->user, $this->workspace);
        $this->presenceService->markOnline($user2, $this->workspace);

        $onlineUsers = $this->presenceService->getOnlineUsers($this->workspace);

        $this->assertCount(2, $onlineUsers);
        $this->assertTrue($onlineUsers->contains($this->user));
        $this->assertTrue($onlineUsers->contains($user2));
        $this->assertFalse($onlineUsers->contains($user3));
    }

    public function test_refresh_extends_ttl(): void
    {
        $this->presenceService->markOnline($this->user, $this->workspace);

        // Wait a moment
        sleep(2);

        // Refresh
        $this->presenceService->refresh($this->user);

        $key = "presence:user:{$this->user->id}";
        $ttl = Redis::ttl($key);

        // TTL should be close to full value again
        $this->assertGreaterThan(55, $ttl);
    }
}
