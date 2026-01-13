<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'notify_level',
        'mobile_push',
        'desktop_push',
        'email',
        'muted_until',
    ];

    protected $casts = [
        'mobile_push' => 'boolean',
        'desktop_push' => 'boolean',
        'email' => 'boolean',
        'muted_until' => 'datetime',
    ];

    /**
     * Get the user that owns this preference
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the conversation this preference belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Check if this conversation is currently muted
     */
    public function isMuted(): bool
    {
        return $this->muted_until && $this->muted_until->isFuture();
    }

    /**
     * Check if user should receive notifications for this conversation
     */
    public function shouldNotify(string $type = 'all'): bool
    {
        // If muted, never notify
        if ($this->isMuted()) {
            return false;
        }

        // Check notify level
        if ($this->notify_level === 'nothing') {
            return false;
        }

        if ($this->notify_level === 'mentions' && $type !== 'mention') {
            return false;
        }

        return true;
    }

    /**
     * Check if push notifications are enabled for a platform
     */
    public function shouldPush(string $platform = 'mobile'): bool
    {
        if ($platform === 'mobile') {
            return $this->mobile_push;
        }

        if ($platform === 'desktop') {
            return $this->desktop_push;
        }

        return false;
    }
}
