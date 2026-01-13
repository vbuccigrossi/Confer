<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'conversation_id',
        'created_by_user_id',
        'target_user_id',
        'message',
        'remind_at',
        'recurrence',
        'is_sent',
        'sent_at',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_sent' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Get pending reminders that need to be sent
     */
    public static function getPendingReminders()
    {
        return static::where('is_sent', false)
            ->where('remind_at', '<=', now())
            ->with(['creator', 'targetUser', 'conversation'])
            ->get();
    }

    /**
     * Mark reminder as sent and handle recurrence
     */
    public function markAsSent(): void
    {
        $this->is_sent = true;
        $this->sent_at = now();
        $this->save();

        // Handle recurring reminders
        if ($this->recurrence) {
            $nextRemindAt = match ($this->recurrence) {
                'daily' => $this->remind_at->addDay(),
                'weekly' => $this->remind_at->addWeek(),
                'monthly' => $this->remind_at->addMonth(),
                'weekdays' => $this->getNextWeekday($this->remind_at),
                default => null,
            };

            if ($nextRemindAt) {
                static::create([
                    'workspace_id' => $this->workspace_id,
                    'conversation_id' => $this->conversation_id,
                    'created_by_user_id' => $this->created_by_user_id,
                    'target_user_id' => $this->target_user_id,
                    'message' => $this->message,
                    'remind_at' => $nextRemindAt,
                    'recurrence' => $this->recurrence,
                ]);
            }
        }
    }

    private function getNextWeekday($date)
    {
        $next = $date->copy()->addDay();
        while ($next->isWeekend()) {
            $next->addDay();
        }
        return $next;
    }
}
