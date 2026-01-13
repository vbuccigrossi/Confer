<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

/**
 * Internal API for Reminder Bot operations.
 * These endpoints are called by the bot server, not by users directly.
 */
class InternalReminderController extends Controller
{
    /**
     * Create a new reminder.
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => 'required|integer|exists:workspaces,id',
            'conversation_id' => 'required|integer|exists:conversations,id',
            'user_id' => 'required|integer|exists:users,id',
            'target_user_id' => 'nullable|integer|exists:users,id',
            'message' => 'required|string|max:1000',
            'remind_at' => 'required|date',
            'recurrence' => 'nullable|string|in:daily,weekly,monthly,weekdays',
        ]);

        $reminder = Reminder::create([
            'workspace_id' => $validated['workspace_id'],
            'conversation_id' => $validated['conversation_id'],
            'created_by_user_id' => $validated['user_id'],
            'target_user_id' => $validated['target_user_id'] ?? $validated['user_id'],
            'message' => $validated['message'],
            'remind_at' => Carbon::parse($validated['remind_at']),
            'recurrence' => $validated['recurrence'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'reminder' => [
                'id' => $reminder->id,
                'message' => $reminder->message,
                'remind_at' => $reminder->remind_at->toIso8601String(),
                'recurrence' => $reminder->recurrence,
            ],
        ]);
    }

    /**
     * List reminders for a user.
     */
    public function list(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => 'required|integer|exists:workspaces,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $reminders = Reminder::where('workspace_id', $validated['workspace_id'])
            ->where(function ($query) use ($validated) {
                $query->where('created_by_user_id', $validated['user_id'])
                    ->orWhere('target_user_id', $validated['user_id']);
            })
            ->where('is_sent', false)
            ->orderBy('remind_at')
            ->get();

        return response()->json([
            'success' => true,
            'reminders' => $reminders->map(function ($r) {
                return [
                    'id' => $r->id,
                    'message' => $r->message,
                    'remind_at' => $r->remind_at->toIso8601String(),
                    'remind_at_human' => $r->remind_at->format('M j, Y \\a\\t g:i A'),
                    'recurrence' => $r->recurrence,
                ];
            }),
        ]);
    }

    /**
     * Delete a reminder.
     */
    public function delete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reminder_id' => 'required|integer|exists:reminders,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $reminder = Reminder::where('id', $validated['reminder_id'])
            ->where(function ($query) use ($validated) {
                $query->where('created_by_user_id', $validated['user_id'])
                    ->orWhere('target_user_id', $validated['user_id']);
            })
            ->first();

        if (!$reminder) {
            return response()->json([
                'success' => false,
                'error' => 'Reminder not found or you do not have permission to delete it.',
            ], 404);
        }

        $reminder->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
