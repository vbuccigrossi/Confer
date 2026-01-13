<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    protected $with = ['user'];

    /**
     * Get the message that owns the reaction
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user that created the reaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter reactions by emoji
     */
    public function scopeByEmoji($query, string $emoji)
    {
        return $query->where('emoji', $emoji);
    }

    /**
     * Scope to filter reactions for a message
     */
    public function scopeForMessage($query, $messageId)
    {
        return $query->where('message_id', $messageId);
    }
}
