<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

/**
 * Internal API for Poll Bot operations.
 * These endpoints are called by the bot server, not by users directly.
 */
class InternalPollController extends Controller
{
    /**
     * Create a new poll.
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => 'required|integer|exists:workspaces,id',
            'conversation_id' => 'required|integer|exists:conversations,id',
            'user_id' => 'required|integer|exists:users,id',
            'question' => 'required|string|max:500',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'required|string|max:200',
            'is_anonymous' => 'boolean',
            'is_multi_select' => 'boolean',
            'closes_at' => 'nullable|date',
        ]);

        $poll = Poll::create([
            'workspace_id' => $validated['workspace_id'],
            'conversation_id' => $validated['conversation_id'],
            'created_by_user_id' => $validated['user_id'],
            'question' => $validated['question'],
            'options' => $validated['options'],
            'is_anonymous' => $validated['is_anonymous'] ?? false,
            'is_multi_select' => $validated['is_multi_select'] ?? false,
            'closes_at' => isset($validated['closes_at']) ? Carbon::parse($validated['closes_at']) : null,
        ]);

        return response()->json([
            'success' => true,
            'poll' => $this->formatPoll($poll, $validated['user_id']),
        ]);
    }

    /**
     * Vote on a poll.
     */
    public function vote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'poll_id' => 'required|integer|exists:polls,id',
            'user_id' => 'required|integer|exists:users,id',
            'option_index' => 'required|integer|min:0',
        ]);

        $poll = Poll::find($validated['poll_id']);

        if (!$poll) {
            return response()->json([
                'success' => false,
                'error' => 'Poll not found.',
            ], 404);
        }

        if ($poll->is_closed) {
            return response()->json([
                'success' => false,
                'error' => 'This poll is closed.',
            ], 400);
        }

        if ($validated['option_index'] >= count($poll->options)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid option number.',
            ], 400);
        }

        // Check if poll should be auto-closed
        if ($poll->shouldAutoClose()) {
            $poll->close();
            return response()->json([
                'success' => false,
                'error' => 'This poll has automatically closed.',
            ], 400);
        }

        $userId = $validated['user_id'];
        $optionIndex = $validated['option_index'];

        // For single-select polls, remove existing votes
        if (!$poll->is_multi_select) {
            PollVote::where('poll_id', $poll->id)
                ->where('user_id', $userId)
                ->delete();
        }

        // Check if already voted for this option (toggle off)
        $existingVote = PollVote::where('poll_id', $poll->id)
            ->where('user_id', $userId)
            ->where('option_index', $optionIndex)
            ->first();

        if ($existingVote) {
            $existingVote->delete();
            return response()->json([
                'success' => true,
                'action' => 'removed',
                'poll' => $this->formatPoll($poll->fresh(), $userId),
            ]);
        }

        // Create new vote
        PollVote::create([
            'poll_id' => $poll->id,
            'user_id' => $userId,
            'option_index' => $optionIndex,
        ]);

        return response()->json([
            'success' => true,
            'action' => 'recorded',
            'poll' => $this->formatPoll($poll->fresh(), $userId),
        ]);
    }

    /**
     * Get poll results.
     */
    public function results(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'poll_id' => 'required|integer|exists:polls,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $poll = Poll::with('creator')->find($validated['poll_id']);

        if (!$poll) {
            return response()->json([
                'success' => false,
                'error' => 'Poll not found.',
            ], 404);
        }

        // Check if poll should be auto-closed
        if ($poll->shouldAutoClose()) {
            $poll->close();
            $poll->refresh();
        }

        return response()->json([
            'success' => true,
            'poll' => $this->formatPoll($poll, $validated['user_id']),
        ]);
    }

    /**
     * Close a poll.
     */
    public function close(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'poll_id' => 'required|integer|exists:polls,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $poll = Poll::find($validated['poll_id']);

        if (!$poll) {
            return response()->json([
                'success' => false,
                'error' => 'Poll not found.',
            ], 404);
        }

        // Only creator can close
        if ($poll->created_by_user_id !== $validated['user_id']) {
            return response()->json([
                'success' => false,
                'error' => 'Only the poll creator can close it.',
            ], 403);
        }

        if ($poll->is_closed) {
            return response()->json([
                'success' => false,
                'error' => 'Poll is already closed.',
            ], 400);
        }

        $poll->close();

        return response()->json([
            'success' => true,
            'poll' => $this->formatPoll($poll->fresh(), $validated['user_id']),
        ]);
    }

    /**
     * Format poll data for response.
     */
    private function formatPoll(Poll $poll, int $userId): array
    {
        $results = $poll->getResults();
        
        // Add voter names for non-anonymous polls
        foreach ($results as $index => &$result) {
            $result['voters'] = $poll->getVotersForOption($index);
        }

        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'options' => $poll->options,
            'is_anonymous' => $poll->is_anonymous,
            'is_multi_select' => $poll->is_multi_select,
            'is_closed' => $poll->is_closed,
            'closes_at' => $poll->closes_at?->format('M j, Y \\a\\t g:i A'),
            'creator_name' => $poll->creator?->name ?? 'Unknown',
            'total_votes' => $poll->getTotalVotes(),
            'results' => $results,
            'your_votes' => $poll->getUserVotes($userId),
        ];
    }
}
