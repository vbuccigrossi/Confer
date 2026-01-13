<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\ConversationMember;
use App\Models\Conversation;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'sound_notifications',
        'last_seen_at',
        'do_not_disturb_until',
        'default_notify_level',
        'notification_keywords',
        'quiet_hours_start',
        'quiet_hours_end',
        'status',
        'status_message',
        'status_emoji',
        'status_expires_at',
        'is_dnd',
        'dnd_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'is_online',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'sound_notifications' => 'boolean',
            'last_seen_at' => 'datetime',
            'do_not_disturb_until' => 'datetime',
            'status_expires_at' => 'datetime',
            'is_dnd' => 'boolean',
            'dnd_until' => 'datetime',
            'notification_keywords' => 'array',
        ];
    }

    /**
     * Boot method to handle cascading deletes
     */
    protected static function boot()
    {
        parent::boot();

        // When a user is deleted, hard delete all their data
        static::deleting(function ($user) {
            // Delete all messages authored by this user
            \App\Models\Message::where('user_id', $user->id)->delete();
            
            // Delete all reactions by this user
            \App\Models\Reaction::where('user_id', $user->id)->delete();
            
            // Delete all conversation memberships
            $user->conversationMemberships()->delete();
            
            // Delete all workspace memberships
            $user->workspaceMemberships()->delete();
            
            // Delete owned workspaces (this will cascade to all workspace data)
            $user->ownedWorkspaces()->delete();
            
            // Delete all API tokens
            $user->tokens()->delete();
            
            // Delete all teams owned by user
            $user->ownedTeams()->delete();
        });
    }

    /**
     * Get workspaces owned by the user
     */
    public function ownedWorkspaces()
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * Get workspace memberships
     */
    public function workspaceMemberships()
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * Get all workspaces the user is a member of
     */
    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get conversation memberships
     */
    public function conversationMemberships()
    {
        return $this->hasMany(ConversationMember::class);
    }

    /**
     * Get all conversations the user is a member of
     */
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_members')
            ->withPivot('role', 'notification_preference', 'joined_at', 'last_read_at')
            ->withTimestamps();
    }

    /**
     * Get the user's device tokens for push notifications
     */
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Route notifications for the FCM channel.
     * Returns an array of device tokens to send notifications to.
     */
    public function routeNotificationForFcm()
    {
        return $this->deviceTokens()
            ->where('last_used_at', '>', now()->subDays(30))
            ->pluck('token')
            ->toArray();
    }

    /**
     * Check if user is currently online (active in last 5 minutes)
     */
    public function isOnline(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }
        return $this->last_seen_at->gt(now()->subMinutes(5));
    }

    /**
     * Accessor for is_online attribute
     */
    public function getIsOnlineAttribute(): bool
    {
        return $this->isOnline();
    }
}
