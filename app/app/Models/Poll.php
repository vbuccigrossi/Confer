<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'conversation_id',
        'created_by_user_id',
        'message_id',
        'question',
        'options',
        'is_anonymous',
        'is_multi_select',
        'is_closed',
        'closes_at',
    ];

    protected $casts = [
        'options' => 'array',
        'is_anonymous' => 'boolean',
        'is_multi_select' => 'boolean',
        'is_closed' => 'boolean',
        'closes_at' => 'datetime',
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

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    /**
     * Get vote counts for each option
     */
    public function getResults(): array
    {
        $voteCounts = $this->votes()
            ->selectRaw('option_index, COUNT(*) as count')
            ->groupBy('option_index')
            ->pluck('count', 'option_index')
            ->toArray();

        $results = [];
        foreach ($this->options as $index => $option) {
            $results[$index] = [
                'option' => $option,
                'votes' => $voteCounts[$index] ?? 0,
            ];
        }

        return $results;
    }

    /**
     * Get total vote count
     */
    public function getTotalVotes(): int
    {
        return $this->votes()->distinct('user_id')->count('user_id');
    }

    /**
     * Get voters for an option (non-anonymous polls only)
     */
    public function getVotersForOption(int $optionIndex): array
    {
        if ($this->is_anonymous) {
            return [];
        }

        return $this->votes()
            ->where('option_index', $optionIndex)
            ->with('user:id,name')
            ->get()
            ->pluck('user.name')
            ->toArray();
    }

    /**
     * Check if user has voted
     */
    public function hasUserVoted(int $userId): bool
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }

    /**
     * Get user's votes
     */
    public function getUserVotes(int $userId): array
    {
        return $this->votes()
            ->where('user_id', $userId)
            ->pluck('option_index')
            ->toArray();
    }

    /**
     * Cast a vote
     */
    public function vote(int $userId, int $optionIndex): bool
    {
        if ($this->is_closed) {
            return false;
        }

        if ($optionIndex < 0 || $optionIndex >= count($this->options)) {
            return false;
        }

        // For single-select polls, remove existing votes
        if (!$this->is_multi_select) {
            $this->votes()->where('user_id', $userId)->delete();
        }

        // Check if already voted for this option
        $existingVote = $this->votes()
            ->where('user_id', $userId)
            ->where('option_index', $optionIndex)
            ->first();

        if ($existingVote) {
            // Toggle - remove the vote
            $existingVote->delete();
            return true;
        }

        PollVote::create([
            'poll_id' => $this->id,
            'user_id' => $userId,
            'option_index' => $optionIndex,
        ]);

        return true;
    }

    /**
     * Close the poll
     */
    public function close(): void
    {
        $this->is_closed = true;
        $this->save();
    }

    /**
     * Check if poll should be auto-closed
     */
    public function shouldAutoClose(): bool
    {
        return $this->closes_at && now()->gte($this->closes_at) && !$this->is_closed;
    }
}
