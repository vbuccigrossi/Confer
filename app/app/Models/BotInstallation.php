<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BotInstallation extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'workspace_id',
        'installed_by_user_id',
        'config',
        'is_active',
        'installed_at',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'installed_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by_user_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(BotToken::class);
    }

    public function slashCommands(): HasMany
    {
        return $this->hasMany(SlashCommand::class);
    }
}
