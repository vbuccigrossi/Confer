<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mention extends Model
{
    protected $fillable = [
        'message_id',
        'mentioned_user_id',
        'mentioned_by_user_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }

    public function mentionedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_by_user_id');
    }
}
