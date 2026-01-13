<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Conversation model - unified channels and DMs
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $type
 * @property string|null $name
 * @property string|null $slug
 * @property string|null $topic
 * @property string|null $description
 * @property int $created_by
 * @property bool $is_archived
 * @property \Illuminate\Support\Carbon|null $archived_at
 */
class Conversation extends Model
{
    use HasFactory;

    const TYPE_PUBLIC_CHANNEL = 'public_channel';
    const TYPE_PRIVATE_CHANNEL = 'private_channel';
    const TYPE_DM = 'dm';
    const TYPE_GROUP_DM = 'group_dm';
    const TYPE_SELF = 'self'; // Notes to Self - user's private notepad

    protected $fillable = [
        'workspace_id',
        'type',
        'name',
        'slug',
        'topic',
        'description',
        'created_by',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($conversation) {
            if ($conversation->isChannel() && $conversation->name && !$conversation->slug) {
                $conversation->slug = Str::slug($conversation->name);
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')
            ->withPivot('role', 'notification_preference', 'joined_at', 'last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isPublic(): bool
    {
        return $this->type === self::TYPE_PUBLIC_CHANNEL;
    }

    public function isPrivate(): bool
    {
        return $this->type === self::TYPE_PRIVATE_CHANNEL;
    }

    public function isDM(): bool
    {
        return $this->type === self::TYPE_DM;
    }

    public function isGroupDM(): bool
    {
        return $this->type === self::TYPE_GROUP_DM;
    }

    public function isSelf(): bool
    {
        return $this->type === self::TYPE_SELF;
    }

    public function isChannel(): bool
    {
        return in_array($this->type, [self::TYPE_PUBLIC_CHANNEL, self::TYPE_PRIVATE_CHANNEL]);
    }

    public function canBeJoinedBy(User $user): bool
    {
        if ($this->isPublic()) {
            return true;
        }
        return false;
    }

    public function addMember(User $user, string $role = 'member'): ConversationMember
    {
        return $this->members()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    public function removeMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->delete() > 0;
    }

    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function scopeChannels($query)
    {
        return $query->whereIn('type', [self::TYPE_PUBLIC_CHANNEL, self::TYPE_PRIVATE_CHANNEL]);
    }

    public function scopeDirectMessages($query)
    {
        return $query->whereIn('type', [self::TYPE_DM, self::TYPE_GROUP_DM]);
    }

    public function scopeInWorkspace($query, int $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function archive(): void
    {
        $this->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);
    }

    public function unarchive(): void
    {
        $this->update([
            'is_archived' => false,
            'archived_at' => null,
        ]);
    }
}
