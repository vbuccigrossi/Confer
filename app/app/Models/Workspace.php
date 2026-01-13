<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Workspace model representing multi-tenant workspaces
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $owner_id
 * @property array|null $settings
 * @property int|null $message_retention_days
 * @property int $storage_quota_mb
 * @property int $storage_used_mb
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Workspace extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'settings',
        'message_retention_days',
    ];

    protected $guarded = [
        'owner_id',
        'storage_quota_mb',
        'storage_used_mb',
    ];

    protected $casts = [
        'settings' => 'array',
        'message_retention_days' => 'integer',
        'storage_quota_mb' => 'integer',
        'storage_used_mb' => 'integer',
    ];

    /**
     * Get the owner of the workspace
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all members of the workspace
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * Get all invites for the workspace
     */
    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    /**
     * Get all apps for the workspace
     */
    public function apps(): HasMany
    {
        return $this->hasMany(App::class);
    }

    /**
     * Get all conversations for the workspace
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
