<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OutboxEvent model for queued webhook/slash command deliveries
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $app_id
 * @property string $event_type
 * @property array $payload
 * @property string $delivery_status
 * @property int $attempt_count
 * @property \Carbon\Carbon|null $last_attempt_at
 * @property string|null $last_error
 */
class OutboxEvent extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    const EVENT_TYPE_SLASH_COMMAND = 'slash_command';
    const EVENT_TYPE_WEBHOOK = 'webhook';

    protected $table = 'events_outbox';

    protected $fillable = [
        'workspace_id',
        'app_id',
        'event_type',
        'payload',
        'delivery_status',
        'attempt_count',
        'last_attempt_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempt_count' => 'integer',
        'last_attempt_at' => 'datetime',
    ];

    /**
     * Get the app this event belongs to
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    /**
     * Get the workspace this event belongs to
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Scope to pending events
     */
    public function scopePending($query)
    {
        return $query->where('delivery_status', self::STATUS_PENDING);
    }

    /**
     * Scope to successful events
     */
    public function scopeSuccess($query)
    {
        return $query->where('delivery_status', self::STATUS_SUCCESS);
    }

    /**
     * Scope to failed events
     */
    public function scopeFailed($query)
    {
        return $query->where('delivery_status', self::STATUS_FAILED);
    }

    /**
     * Mark event as successful
     */
    public function markSuccess(): bool
    {
        return $this->update([
            'delivery_status' => self::STATUS_SUCCESS,
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Mark event as failed
     */
    public function markFailed(string $error): bool
    {
        return $this->update([
            'delivery_status' => self::STATUS_FAILED,
            'last_error' => $error,
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Increment attempt count
     */
    public function incrementAttempts(): bool
    {
        return $this->update([
            'attempt_count' => $this->attempt_count + 1,
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Check if event is pending
     */
    public function isPending(): bool
    {
        return $this->delivery_status === self::STATUS_PENDING;
    }

    /**
     * Check if event succeeded
     */
    public function isSuccess(): bool
    {
        return $this->delivery_status === self::STATUS_SUCCESS;
    }

    /**
     * Check if event failed
     */
    public function isFailed(): bool
    {
        return $this->delivery_status === self::STATUS_FAILED;
    }
}
