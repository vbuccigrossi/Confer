<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'message_id',
        'notify_all_replies',
    ];

    protected $casts = [
        'notify_all_replies' => 'boolean',
    ];

    /**
     * Get the user who owns the subscription
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the message (thread) being subscribed to
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
