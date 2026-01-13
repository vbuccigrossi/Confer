<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Invite model for workspace invitations
 *
 * @property int $id
 * @property int $workspace_id
 * @property string|null $email
 * @property string $token
 * @property string|null $invite_code
 * @property int|null $max_uses
 * @property int $use_count
 * @property bool $is_single_use
 * @property int $invited_by
 * @property string $role
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Invite extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'email',
        'invited_by',
        'role',
        'accepted_at',
        'expires_at',
        'invite_code',
        'max_uses',
        'use_count',
        'is_single_use',
    ];

    protected $guarded = [
        'token',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_single_use' => 'boolean',
        'use_count' => 'integer',
        'max_uses' => 'integer',
    ];

    /**
     * Boot model and generate token
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invite) {
            if (empty($invite->token)) {
                $invite->token = Str::random(64);
            }
            if (empty($invite->expires_at)) {
                $invite->expires_at = now()->addDays(7);
            }
            // Auto-generate invite code if not single-use and not provided
            if (!$invite->is_single_use && empty($invite->invite_code)) {
                $invite->invite_code = strtoupper(Str::random(8));
            }
        });
    }

    /**
     * Get the workspace
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the inviter
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if invite is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if invite is accepted
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Check if invite is valid (not expired and not accepted)
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isAccepted();
    }

    /**
     * Check if invite code can be used (for reusable codes)
     */
    public function canBeUsed(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        // Single-use invites can only be used if not yet accepted
        if ($this->is_single_use) {
            return !$this->isAccepted();
        }

        // Reusable codes check max_uses
        if ($this->max_uses !== null && $this->use_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Increment use count for reusable invite codes
     */
    public function incrementUseCount(): void
    {
        $this->increment('use_count');
    }

    /**
     * Find valid invite by code
     */
    public static function findByCode(string $code): ?self
    {
        return self::where('invite_code', $code)
            ->where('is_single_use', false)
            ->where(function ($query) {
                $query->whereNull('max_uses')
                      ->orWhereRaw('use_count < max_uses');
            })
            ->where('expires_at', '>', now())
            ->first();
    }
}
