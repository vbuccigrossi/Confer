<?php

namespace App\Services;

use App\Models\User;
use App\Events\UserStatusChanged;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class StatusService
{
    /**
     * Available status options
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_AWAY = 'away';
    public const STATUS_DND = 'dnd';
    public const STATUS_INVISIBLE = 'invisible';

    /**
     * Update user's status
     */
    public function setStatus(
        User $user,
        string $status,
        ?string $message = null,
        ?string $emoji = null,
        ?Carbon $expiresAt = null
    ): User {
        $user->update([
            'status' => $status,
            'status_message' => $message,
            'status_emoji' => $emoji,
            'status_expires_at' => $expiresAt,
        ]);

        // If setting to DND, also set the DND flag
        if ($status === self::STATUS_DND) {
            $user->update([
                'is_dnd' => true,
                'dnd_until' => $expiresAt,
            ]);
        } else {
            // Clear DND if switching to another status
            $user->update([
                'is_dnd' => false,
                'dnd_until' => null,
            ]);
        }

        // Broadcast status change
        broadcast(new UserStatusChanged($user))->toOthers();

        // Clear any cached presence data
        $this->clearPresenceCache($user->id);

        return $user->fresh();
    }

    /**
     * Clear user's custom status message
     */
    public function clearStatus(User $user): User
    {
        $user->update([
            'status' => self::STATUS_ACTIVE,
            'status_message' => null,
            'status_emoji' => null,
            'status_expires_at' => null,
        ]);

        broadcast(new UserStatusChanged($user))->toOthers();
        $this->clearPresenceCache($user->id);

        return $user->fresh();
    }

    /**
     * Enable Do Not Disturb mode
     */
    public function enableDnd(User $user, ?Carbon $until = null): User
    {
        $user->update([
            'status' => self::STATUS_DND,
            'is_dnd' => true,
            'dnd_until' => $until,
        ]);

        broadcast(new UserStatusChanged($user))->toOthers();
        $this->clearPresenceCache($user->id);

        return $user->fresh();
    }

    /**
     * Disable Do Not Disturb mode
     */
    public function disableDnd(User $user): User
    {
        $user->update([
            'status' => self::STATUS_ACTIVE,
            'is_dnd' => false,
            'dnd_until' => null,
        ]);

        broadcast(new UserStatusChanged($user))->toOthers();
        $this->clearPresenceCache($user->id);

        return $user->fresh();
    }

    /**
     * Check if user should receive notifications
     */
    public function shouldReceiveNotification(User $user): bool
    {
        // Check if DND is active and not expired
        if ($user->is_dnd) {
            if ($user->dnd_until && now()->greaterThan($user->dnd_until)) {
                // DND expired, auto-disable it
                $this->disableDnd($user);
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * Get user's current effective status
     */
    public function getEffectiveStatus(User $user): array
    {
        // Check if status has expired
        if ($user->status_expires_at && now()->greaterThan($user->status_expires_at)) {
            $this->clearStatus($user);
            $user->refresh();
        }

        // Check if DND has expired
        if ($user->is_dnd && $user->dnd_until && now()->greaterThan($user->dnd_until)) {
            $this->disableDnd($user);
            $user->refresh();
        }

        return [
            'status' => $user->status,
            'status_message' => $user->status_message,
            'status_emoji' => $user->status_emoji,
            'is_dnd' => $user->is_dnd,
            'is_online' => $user->is_online, // Uses existing presence logic
        ];
    }

    /**
     * Set user as away (auto-triggered by inactivity)
     */
    public function setAway(User $user): User
    {
        // Only auto-set to away if they're currently active
        if ($user->status === self::STATUS_ACTIVE) {
            $user->update(['status' => self::STATUS_AWAY]);
            broadcast(new UserStatusChanged($user))->toOthers();
            $this->clearPresenceCache($user->id);
        }

        return $user->fresh();
    }

    /**
     * Set user back to active (auto-triggered by activity)
     */
    public function setActive(User $user): User
    {
        // Only auto-set to active if they're currently away
        if ($user->status === self::STATUS_AWAY) {
            $user->update(['status' => self::STATUS_ACTIVE]);
            broadcast(new UserStatusChanged($user))->toOthers();
            $this->clearPresenceCache($user->id);
        }

        return $user->fresh();
    }

    /**
     * Clear presence cache for user
     */
    private function clearPresenceCache(int $userId): void
    {
        Cache::forget("user_status_{$userId}");
    }

    /**
     * Get all available status presets
     */
    public function getStatusPresets(): array
    {
        return [
            [
                'status' => self::STATUS_ACTIVE,
                'message' => null,
                'emoji' => null,
                'label' => 'Active',
            ],
            [
                'status' => self::STATUS_AWAY,
                'message' => 'Away',
                'emoji' => '💤',
                'label' => 'Away',
            ],
            [
                'status' => self::STATUS_DND,
                'message' => 'Do not disturb',
                'emoji' => '🔕',
                'label' => 'Do Not Disturb',
            ],
            [
                'status' => self::STATUS_ACTIVE,
                'message' => 'In a meeting',
                'emoji' => '📅',
                'label' => 'In a meeting',
            ],
            [
                'status' => self::STATUS_ACTIVE,
                'message' => 'On a call',
                'emoji' => '📞',
                'label' => 'On a call',
            ],
            [
                'status' => self::STATUS_ACTIVE,
                'message' => 'Commuting',
                'emoji' => '🚗',
                'label' => 'Commuting',
            ],
            [
                'status' => self::STATUS_ACTIVE,
                'message' => 'Out sick',
                'emoji' => '🤒',
                'label' => 'Out sick',
            ],
            [
                'status' => self::STATUS_ACTIVE,
                'message' => 'On vacation',
                'emoji' => '🏖️',
                'label' => 'On vacation',
            ],
            [
                'status' => self::STATUS_ACTIVE,
                'message' => 'Working remotely',
                'emoji' => '🏡',
                'label' => 'Working remotely',
            ],
        ];
    }
}
