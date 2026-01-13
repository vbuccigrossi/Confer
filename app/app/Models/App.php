<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * App model for integrations (webhooks, bots, slash commands)
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $type
 * @property string $client_id
 * @property string|null $client_secret (hashed)
 * @property string $token (hashed)
 * @property array $scopes
 * @property string|null $callback_url
 * @property int|null $default_conversation_id
 * @property int $created_by
 * @property bool $is_active
 */
class App extends Model
{
    use HasFactory;

    const TYPE_BOT = 'bot';
    const TYPE_WEBHOOK = 'webhook';
    const TYPE_SLASH = 'slash';

    const SCOPE_CHAT_WRITE = 'chat:write';
    const SCOPE_CHANNELS_READ = 'channels:read';
    const SCOPE_CHANNELS_WRITE_JOINED = 'channels:write:joined';

    protected $fillable = [
        'workspace_id',
        'name',
        'type',
        'scopes',
        'callback_url',
        'default_conversation_id',
        'created_by',
        'is_active',
    ];

    protected $guarded = [
        'client_id',
        'client_secret',
        'token',
    ];

    protected $hidden = [
        'client_secret',
        'token',
    ];

    protected $casts = [
        'scopes' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the workspace this app belongs to
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user who created this app
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the default conversation for webhooks
     */
    public function defaultConversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'default_conversation_id');
    }

    /**
     * Get all outbox events for this app
     */
    public function outboxEvents(): HasMany
    {
        return $this->hasMany(OutboxEvent::class);
    }

    /**
     * Get all audit logs for this app
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Verify a plain token against the hashed token
     */
    public function verifyToken(string $plainToken): bool
    {
        return Hash::check($plainToken, $this->token);
    }

    /**
     * Check if app has a specific scope
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    /**
     * Get scopes as array
     */
    public function getScopes(): array
    {
        return $this->scopes ?? [];
    }

    /**
     * Check if app is a webhook
     */
    public function isWebhook(): bool
    {
        return $this->type === self::TYPE_WEBHOOK;
    }

    /**
     * Check if app is a bot
     */
    public function isBot(): bool
    {
        return $this->type === self::TYPE_BOT;
    }

    /**
     * Check if app is a slash command
     */
    public function isSlashCommand(): bool
    {
        return $this->type === self::TYPE_SLASH;
    }

    /**
     * Generate a unique client ID
     */
    public static function generateClientId(): string
    {
        return 'app_' . Str::random(32);
    }

    /**
     * Generate a secure token
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }
}
