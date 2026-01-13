<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SlashCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_installation_id',
        'command',
        'description',
        'usage_hint',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(BotInstallation::class, 'bot_installation_id');
    }

    public function bot(): BelongsTo
    {
        return $this->installation->bot();
    }
}
