<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\MarkdownService;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'parent_message_id',
        'body_md',
        'body_html',
        'edited_at',
        'is_system',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
        'is_system' => 'boolean',
    ];

    protected $with = ['user'];

    /**
     * Boot method to auto-render Markdown and cascade deletes
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($message) {
            if ($message->body_md && !$message->body_html) {
                $message->body_html = app(MarkdownService::class)->parse($message->body_md);
            }
        });

        static::updating(function ($message) {
            if ($message->isDirty('body_md')) {
                $message->body_html = app(MarkdownService::class)->parse($message->body_md);
            }
        });

        // Update parent thread metadata when reply is created
        static::created(function ($message) {
            if ($message->parent_message_id) {
                $parent = Message::find($message->parent_message_id);
                if ($parent) {
                    $parent->increment('reply_count');
                    $parent->update([
                        'last_reply_at' => $message->created_at,
                        'last_reply_user_id' => $message->user_id,
                    ]);

                    // Auto-subscribe the replier to the thread
                    $message->subscribeUserToThread($message->user_id);
                }
            }
        });

        // Update parent thread metadata when reply is deleted
        static::deleted(function ($message) {
            if ($message->parent_message_id) {
                $parent = Message::find($message->parent_message_id);
                if ($parent) {
                    $parent->decrement('reply_count');

                    // Update last_reply_at to the most recent remaining reply
                    $lastReply = $parent->replies()->latest()->first();
                    if ($lastReply) {
                        $parent->update([
                            'last_reply_at' => $lastReply->created_at,
                            'last_reply_user_id' => $lastReply->user_id,
                        ]);
                    } else {
                        $parent->update([
                            'last_reply_at' => null,
                            'last_reply_user_id' => null,
                        ]);
                    }
                }
            }
        });

        // Hard delete all related data when message is deleted
        static::deleting(function ($message) {
            // Delete all reactions
            $message->reactions()->delete();

            // Delete all attachments
            $message->attachments()->delete();

            // Delete all mentions
            $message->mentions()->delete();

            // Delete all thread subscriptions
            $message->threadSubscriptions()->delete();

            // Delete all replies (thread messages)
            $message->replies()->delete();
        });
    }

    /**
     * Get the conversation that owns the message
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user that authored the message
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent message (for threads)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_message_id');
    }

    /**
     * Get the replies to this message
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_message_id')->orderBy('created_at');
    }

    /**
     * Get all attachments for this message
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Get all reactions for this message
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    /**
     * Get all mentions for this message
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }

    /**
     * Get all link previews for this message
     */
    public function linkPreviews(): HasMany
    {
        return $this->hasMany(LinkPreview::class);
    }

    /**
     * Scope to filter messages by conversation
     */
    public function scopeInConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * Scope to get only root messages (not replies)
     */
    public function scopeRootMessages($query)
    {
        return $query->whereNull('parent_message_id');
    }

    /**
     * Scope to get only thread replies
     */
    public function scopeThreads($query)
    {
        return $query->whereNotNull('parent_message_id');
    }

    /**
     * Check if the message is editable by the given user
     */
    public function isEditable(User $user): bool
    {
        // Check if user is the author
        if ($this->user_id === $user->id) {
            // Author can edit within 15 minutes
            return $this->created_at->diffInMinutes(now()) <= 15;
        }

        // Check if user is admin or owner of the conversation
        $membership = $this->conversation->members()
            ->where('user_id', $user->id)
            ->first();

        return $membership && in_array($membership->role, ['owner', 'admin']);
    }

    /**
     * Check if the message is deletable by the given user
     */
    public function isDeletable(User $user): bool
    {
        return $this->isEditable($user);
    }

    /**
     * Get the count of replies to this message
     */
    public function replyCount(): int
    {
        return $this->replies()->count();
    }

    /**
     * Check if this message is a thread reply
     */
    public function isThread(): bool
    {
        return $this->parent_message_id !== null;
    }

    /**
     * Extract mentions from the message body
     */
    public function extractMentions(): array
    {
        return app(MarkdownService::class)->extractMentions($this->body_md);
    }

    /**
     * Get thread subscriptions for this message
     */
    public function threadSubscriptions(): HasMany
    {
        return $this->hasMany(ThreadSubscription::class);
    }

    /**
     * Get the user who made the last reply
     */
    public function lastReplyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reply_user_id');
    }

    /**
     * Subscribe a user to this thread
     */
    public function subscribeUserToThread(int $userId): void
    {
        ThreadSubscription::firstOrCreate([
            'user_id' => $userId,
            'message_id' => $this->parent_message_id ?? $this->id,
        ], [
            'notify_all_replies' => true,
        ]);
    }

    /**
     * Unsubscribe a user from this thread
     */
    public function unsubscribeUserFromThread(int $userId): void
    {
        ThreadSubscription::where('user_id', $userId)
            ->where('message_id', $this->parent_message_id ?? $this->id)
            ->delete();
    }

    /**
     * Check if a user is subscribed to this thread
     */
    public function isUserSubscribed(int $userId): bool
    {
        return ThreadSubscription::where('user_id', $userId)
            ->where('message_id', $this->parent_message_id ?? $this->id)
            ->exists();
    }
}
