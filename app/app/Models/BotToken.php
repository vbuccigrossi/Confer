<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class BotToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_installation_id',
        'token',
        'name',
        'scopes',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(BotInstallation::class, 'bot_installation_id');
    }

    public static function generateToken(): string
    {
        return 'bot_' . Str::random(60);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
