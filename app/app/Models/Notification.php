<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    const TYPE_MENTION = 'mention';
    const TYPE_THREAD_REPLY = 'thread_reply';
    const TYPE_KEYWORD = 'keyword';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'type',
        'actor_user_id',
        'conversation_id',
        'message_id',
        'payload',
        'is_read',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_read' => 'boolean',
    ];

    /**
     * Get the user who receives this notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who triggered this notification
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Get the conversation this notification belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the message that triggered this notification
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the workspace this notification belongs to
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Scope to unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope to notifications for a specific user
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope to notifications by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Mark this notification as read
     */
    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]);
    }

    /**
     * Check if notification is a mention
     */
    public function isMention(): bool
    {
        return $this->type === self::TYPE_MENTION;
    }

    /**
     * Check if notification is a thread reply
     */
    public function isThreadReply(): bool
    {
        return $this->type === self::TYPE_THREAD_REPLY;
    }

    /**
     * Check if notification is a keyword match
     */
    public function isKeyword(): bool
    {
        return $this->type === self::TYPE_KEYWORD;
    }
}
