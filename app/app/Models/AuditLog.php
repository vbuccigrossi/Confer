<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * AuditLog model for tracking app integration activities
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $app_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class AuditLog extends Model
{
    use HasFactory;

    const ACTION_WEBHOOK_POSTED = 'webhook.posted';
    const ACTION_SLASH_COMMAND_INVOKED = 'slash_command.invoked';
    const ACTION_BOT_MESSAGE_POSTED = 'bot.message_posted';
    const ACTION_APP_CREATED = 'app.created';
    const ACTION_APP_TOKEN_REGENERATED = 'app.token_regenerated';
    const ACTION_APP_DELETED = 'app.deleted';

    protected $fillable = [
        'workspace_id',
        'app_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the workspace this log belongs to
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the app this log belongs to
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    /**
     * Get the user associated with this log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subject (polymorphic)
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to specific action
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to specific workspace
     */
    public function scopeForWorkspace($query, int $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope to specific app
     */
    public function scopeForApp($query, int $appId)
    {
        return $query->where('app_id', $appId);
    }

    /**
     * Create a webhook posted log
     */
    public static function logWebhookPosted(App $app, Message $message, ?string $ipAddress, ?string $userAgent): self
    {
        return self::create([
            'workspace_id' => $app->workspace_id,
            'app_id' => $app->id,
            'action' => self::ACTION_WEBHOOK_POSTED,
            'subject_type' => Message::class,
            'subject_id' => $message->id,
            'metadata' => [
                'conversation_id' => $message->conversation_id,
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Create a slash command invoked log
     */
    public static function logSlashCommandInvoked(App $app, User $user, string $command, array $payload, ?string $ipAddress, ?string $userAgent): self
    {
        return self::create([
            'workspace_id' => $app->workspace_id,
            'app_id' => $app->id,
            'user_id' => $user->id,
            'action' => self::ACTION_SLASH_COMMAND_INVOKED,
            'metadata' => [
                'command' => $command,
                'conversation_id' => $payload['conversation_id'] ?? null,
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Create a bot message posted log
     */
    public static function logBotMessagePosted(App $app, Message $message, ?string $ipAddress, ?string $userAgent): self
    {
        return self::create([
            'workspace_id' => $app->workspace_id,
            'app_id' => $app->id,
            'action' => self::ACTION_BOT_MESSAGE_POSTED,
            'subject_type' => Message::class,
            'subject_id' => $message->id,
            'metadata' => [
                'conversation_id' => $message->conversation_id,
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
