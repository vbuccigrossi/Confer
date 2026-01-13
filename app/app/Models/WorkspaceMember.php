<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorkspaceMember pivot model
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property string $role
 * @property \Illuminate\Support\Carbon $joined_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WorkspaceMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'joined_at',
    ];

    protected $guarded = [
        'workspace_id',
        'user_id',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    /**
     * Get the workspace
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
