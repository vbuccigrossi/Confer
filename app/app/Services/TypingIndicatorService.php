<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class TypingIndicatorService
{
    /**
     * TTL for typing indicator in seconds
     */
    private const TTL = 5;

    /**
     * Mark user as typing in a conversation
     */
    public function startTyping(int $conversationId, int $userId, string $userName): void
    {
        $key = "typing:conversation:{$conversationId}";

        // Store user info with TTL
        Redis::hset($key, $userId, json_encode([
            'user_id' => $userId,
            'user_name' => $userName,
            'started_at' => now()->toIso8601String(),
        ]));

        // Set expiration on the hash key
        Redis::expire($key, self::TTL * 2);

        // Set individual field TTL using a separate key
        Redis::setex("typing:user:{$conversationId}:{$userId}", self::TTL, '1');
    }

    /**
     * Mark user as stopped typing
     */
    public function stopTyping(int $conversationId, int $userId): void
    {
        $key = "typing:conversation:{$conversationId}";
        Redis::hdel($key, (string) $userId);
        Redis::del("typing:user:{$conversationId}:{$userId}");
    }

    /**
     * Get list of users currently typing in a conversation
     */
    public function getTypingUsers(int $conversationId, int $excludeUserId = null): array
    {
        $key = "typing:conversation:{$conversationId}";
        $typingData = Redis::hgetall($key);

        if (empty($typingData)) {
            return [];
        }

        $typingUsers = [];

        foreach ($typingData as $userId => $data) {
            // Skip excluded user (usually the current user)
            if ($excludeUserId && $userId == $excludeUserId) {
                continue;
            }

            // Check if user's typing TTL has expired
            if (!Redis::exists("typing:user:{$conversationId}:{$userId}")) {
                // Clean up expired entry
                Redis::hdel($key, (string) $userId);
                continue;
            }

            $userData = json_decode($data, true);
            if ($userData) {
                $typingUsers[] = $userData;
            }
        }

        return $typingUsers;
    }

    /**
     * Clear all typing indicators for a conversation
     */
    public function clearConversation(int $conversationId): void
    {
        $key = "typing:conversation:{$conversationId}";
        $userIds = Redis::hkeys($key);

        foreach ($userIds as $userId) {
            Redis::del("typing:user:{$conversationId}:{$userId}");
        }

        Redis::del($key);
    }

    /**
     * Get formatted typing message
     */
    public function getTypingMessage(array $typingUsers): ?string
    {
        $count = count($typingUsers);

        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return "{$typingUsers[0]['user_name']} is typing...";
        }

        if ($count === 2) {
            return "{$typingUsers[0]['user_name']} and {$typingUsers[1]['user_name']} are typing...";
        }

        return "{$typingUsers[0]['user_name']} and " . ($count - 1) . " others are typing...";
    }
}
