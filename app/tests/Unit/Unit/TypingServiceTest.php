<?php

namespace Tests\Unit\Unit;

use Tests\TestCase;
use App\Services\TypingService;
use App\Models\User;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Event;
use App\Events\TypingStartedEvent;
use App\Events\TypingStoppedEvent;

class TypingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TypingService $typingService;
    protected User $user;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typingService = app(TypingService::class);
        $this->user = User::factory()->create();
        $this->conversation = Conversation::factory()->create();

        // Clear Redis before each test
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_start_typing_adds_user_to_sorted_set(): void
    {
        $this->typingService->startTyping($this->user, $this->conversation);

        $key = "typing:conv:{$this->conversation->id}";
        $members = Redis::zrange($key, 0, -1);

        $this->assertContains((string)$this->user->id, $members);
    }

    public function test_start_typing_broadcasts_event(): void
    {
        Event::fake();

        $this->typingService->startTyping($this->user, $this->conversation);

        Event::assertDispatched(TypingStartedEvent::class, function ($event) {
            return $event->user->id === $this->user->id
                && $event->conversation->id === $this->conversation->id;
        });
    }

    public function test_stop_typing_removes_user_from_sorted_set(): void
    {
        $this->typingService->startTyping($this->user, $this->conversation);
        $this->typingService->stopTyping($this->user, $this->conversation);

        $key = "typing:conv:{$this->conversation->id}";
        $members = Redis::zrange($key, 0, -1);

        $this->assertNotContains((string)$this->user->id, $members);
    }

    public function test_stop_typing_broadcasts_event(): void
    {
        Event::fake();

        $this->typingService->startTyping($this->user, $this->conversation);
        $this->typingService->stopTyping($this->user, $this->conversation);

        Event::assertDispatched(TypingStoppedEvent::class, function ($event) {
            return $event->user->id === $this->user->id
                && $event->conversation->id === $this->conversation->id;
        });
    }

    public function test_is_typing_returns_true_when_user_typing(): void
    {
        $this->typingService->startTyping($this->user, $this->conversation);

        $this->assertTrue($this->typingService->isTyping($this->user, $this->conversation));
    }

    public function test_is_typing_returns_false_when_user_not_typing(): void
    {
        $this->assertFalse($this->typingService->isTyping($this->user, $this->conversation));
    }

    public function test_get_typing_users_returns_typing_users(): void
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $this->typingService->startTyping($this->user, $this->conversation);
        $this->typingService->startTyping($user2, $this->conversation);

        $typingUsers = $this->typingService->getTypingUsers($this->conversation);

        $this->assertCount(2, $typingUsers);
        $this->assertTrue($typingUsers->contains($this->user));
        $this->assertTrue($typingUsers->contains($user2));
        $this->assertFalse($typingUsers->contains($user3));
    }

    public function test_get_typing_users_excludes_expired_entries(): void
    {
        // Start typing
        $this->typingService->startTyping($this->user, $this->conversation);

        // Manually set timestamp to expired value (older than TTL)
        $key = "typing:conv:{$this->conversation->id}";
        $expiredTime = now()->subSeconds(10)->timestamp;
        Redis::zadd($key, $expiredTime, $this->user->id);

        // Should return empty since entry is expired
        $typingUsers = $this->typingService->getTypingUsers($this->conversation);

        $this->assertCount(0, $typingUsers);
    }
}
