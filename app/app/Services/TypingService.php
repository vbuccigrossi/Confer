<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class TypingService
{
    protected int $ttl;

    public function __construct()
    {
        $this->ttl = config('presence.typing_ttl_seconds', 5);
    }

    /**
     * Mark a user as typing in a conversation
     */
    public function startTyping(User $user, Conversation $conversation): void
    {
        $key = $this->getConversationKey($conversation->id);
        $value = json_encode([
            'user_id' => $user->id,
            'started_at' => now()->toIso8601String(),
        ]);

        // Add to set with score (timestamp)
        Redis::zadd($key, now()->timestamp, $user->id);
        Redis::expire($key, $this->ttl + 10);

        // Broadcast event
        event(new \App\Events\TypingStartedEvent($user, $conversation));
    }

    /**
     * Mark a user as stopped typing in a conversation
     */
    public function stopTyping(User $user, Conversation $conversation): void
    {
        $key = $this->getConversationKey($conversation->id);
        Redis::zrem($key, $user->id);

        // Broadcast event
        event(new \App\Events\TypingStoppedEvent($user, $conversation));
    }

    /**
     * Get all users currently typing in a conversation
     */
    public function getTypingUsers(Conversation $conversation): Collection
    {
        $key = $this->getConversationKey($conversation->id);
        
        // Remove expired entries (older than TTL)
        $cutoff = now()->subSeconds($this->ttl)->timestamp;
        Redis::zremrangebyscore($key, '-inf', $cutoff);

        // Get remaining user IDs
        $userIds = Redis::zrange($key, 0, -1);

        if (empty($userIds)) {
            return collect([]);
        }

        return User::whereIn('id', $userIds)->get();
    }

    /**
     * Check if a specific user is typing
     */
    public function isTyping(User $user, Conversation $conversation): bool
    {
        $key = $this->getConversationKey($conversation->id);
        $score = Redis::zscore($key, $user->id);

        if ($score === null) {
            return false;
        }

        // Check if not expired
        $cutoff = now()->subSeconds($this->ttl)->timestamp;
        return $score >= $cutoff;
    }

    /**
     * Get Redis key for conversation typing
     */
    protected function getConversationKey(int $conversationId): string
    {
        return "typing:conv:{$conversationId}";
    }
}
