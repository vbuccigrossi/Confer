<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class PresenceService
{
    protected int $ttl;

    public function __construct()
    {
        $this->ttl = config('presence.ttl_seconds', 60);
    }

    /**
     * Mark a user as online
     */
    public function markOnline(User $user, Workspace $workspace): void
    {
        $key = $this->getUserKey($user->id);
        $data = [
            'workspace_id' => $workspace->id,
            'last_seen' => now()->toIso8601String(),
        ];

        Redis::setex($key, $this->ttl, json_encode($data));

        // Add to workspace online set
        $workspaceKey = $this->getWorkspaceKey($workspace->id);
        Redis::sadd($workspaceKey, $user->id);
        Redis::expire($workspaceKey, $this->ttl + 10);

        // Broadcast online event
        event(new \App\Events\PresenceUserOnlineEvent($user, $workspace));
    }

    /**
     * Mark a user as offline
     */
    public function markOffline(User $user): void
    {
        $key = $this->getUserKey($user->id);
        $data = Redis::get($key);

        if ($data) {
            $payload = json_decode($data, true);
            $workspaceId = $payload['workspace_id'] ?? null;

            // Remove from workspace set
            if ($workspaceId) {
                $workspaceKey = $this->getWorkspaceKey($workspaceId);
                Redis::srem($workspaceKey, $user->id);
                
                // Broadcast offline event
                $workspace = Workspace::find($workspaceId);
                if ($workspace) {
                    event(new \App\Events\PresenceUserOfflineEvent($user, $workspace));
                }
            }
        }

        Redis::del($key);
    }

    /**
     * Check if a user is online
     */
    public function isOnline(User $user): bool
    {
        $key = $this->getUserKey($user->id);
        return Redis::exists($key) > 0;
    }

    /**
     * Get all online users in a workspace
     */
    public function getOnlineUsers(Workspace $workspace): Collection
    {
        $workspaceKey = $this->getWorkspaceKey($workspace->id);
        $userIds = Redis::smembers($workspaceKey);

        if (empty($userIds)) {
            return collect([]);
        }

        return User::whereIn('id', $userIds)->get();
    }

    /**
     * Get presence status for conversation members
     */
    public function getConversationPresence(Conversation $conversation): array
    {
        $members = $conversation->members()->with('user')->get();
        $presence = [];

        foreach ($members as $member) {
            $presence[$member->user_id] = [
                'user_id' => $member->user_id,
                'is_online' => $this->isOnline($member->user),
                'last_seen' => $this->getLastSeen($member->user),
            ];
        }

        return $presence;
    }

    /**
     * Get last seen timestamp for a user
     */
    public function getLastSeen(User $user): ?string
    {
        $key = $this->getUserKey($user->id);
        $data = Redis::get($key);

        if ($data) {
            $payload = json_decode($data, true);
            return $payload['last_seen'] ?? null;
        }

        return null;
    }

    /**
     * Refresh presence TTL (called on activity)
     */
    public function refresh(User $user): void
    {
        $key = $this->getUserKey($user->id);
        
        if (Redis::exists($key)) {
            Redis::expire($key, $this->ttl);
            
            // Update last_seen timestamp
            $data = Redis::get($key);
            if ($data) {
                $payload = json_decode($data, true);
                $payload['last_seen'] = now()->toIso8601String();
                Redis::setex($key, $this->ttl, json_encode($payload));
            }
        }
    }

    /**
     * Get Redis key for user presence
     */
    protected function getUserKey(int $userId): string
    {
        return "presence:user:{$userId}";
    }

    /**
     * Get Redis key for workspace online users
     */
    protected function getWorkspaceKey(int $workspaceId): string
    {
        return "presence:workspace:{$workspaceId}";
    }
}
