<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationPreferenceController extends Controller
{
    /**
     * Get all notification preferences for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $preferences = NotificationPreference::where('user_id', $request->user()->id)
            ->with('conversation:id,name')
            ->get();

        return response()->json([
            'preferences' => $preferences,
        ]);
    }

    /**
     * Get notification preference for a specific conversation
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $preference = NotificationPreference::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
            ],
            [
                'notify_level' => $request->user()->default_notify_level ?? 'all',
                'mobile_push' => true,
                'desktop_push' => true,
                'email' => false,
            ]
        );

        return response()->json($preference);
    }

    /**
     * Update notification preference for a conversation
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'notify_level' => 'sometimes|in:all,mentions,nothing',
            'mobile_push' => 'sometimes|boolean',
            'desktop_push' => 'sometimes|boolean',
            'email' => 'sometimes|boolean',
            'muted_until' => 'sometimes|nullable|date',
        ]);

        $preference = NotificationPreference::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
            ],
            $validated
        );

        return response()->json($preference);
    }

    /**
     * Mute a conversation for a specific duration
     */
    public function mute(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'duration' => 'required|in:30m,1h,4h,24h,forever',
        ]);

        $mutedUntil = match ($validated['duration']) {
            '30m' => now()->addMinutes(30),
            '1h' => now()->addHour(),
            '4h' => now()->addHours(4),
            '24h' => now()->addDay(),
            'forever' => now()->addYears(10), // Effectively forever
        };

        $preference = NotificationPreference::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
            ],
            [
                'muted_until' => $mutedUntil,
            ]
        );

        return response()->json([
            'message' => 'Conversation muted',
            'muted_until' => $preference->muted_until,
        ]);
    }

    /**
     * Unmute a conversation
     */
    public function unmute(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $preference = NotificationPreference::where('user_id', $request->user()->id)
            ->where('conversation_id', $conversation->id)
            ->first();

        if ($preference) {
            $preference->update(['muted_until' => null]);
        }

        return response()->json([
            'message' => 'Conversation unmuted',
        ]);
    }

    /**
     * Get user's global notification settings
     */
    public function getGlobalSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'do_not_disturb_until' => $user->do_not_disturb_until,
            'default_notify_level' => $user->default_notify_level,
            'notification_keywords' => $user->notification_keywords,
            'quiet_hours_start' => $user->quiet_hours_start,
            'quiet_hours_end' => $user->quiet_hours_end,
        ]);
    }

    /**
     * Update user's global notification settings
     */
    public function updateGlobalSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'default_notify_level' => 'sometimes|in:all,mentions,nothing',
            'notification_keywords' => 'sometimes|array',
            'notification_keywords.*' => 'string|max:50',
            'quiet_hours_start' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_end' => 'sometimes|nullable|date_format:H:i',
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Global notification settings updated',
            'settings' => [
                'default_notify_level' => $request->user()->default_notify_level,
                'notification_keywords' => $request->user()->notification_keywords,
                'quiet_hours_start' => $request->user()->quiet_hours_start,
                'quiet_hours_end' => $request->user()->quiet_hours_end,
            ],
        ]);
    }

    /**
     * Enable Do Not Disturb mode
     */
    public function enableDnd(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'duration' => 'required|in:30m,1h,4h,24h,custom',
            'until' => 'required_if:duration,custom|nullable|date',
        ]);

        $dndUntil = match ($validated['duration']) {
            '30m' => now()->addMinutes(30),
            '1h' => now()->addHour(),
            '4h' => now()->addHours(4),
            '24h' => now()->addDay(),
            'custom' => $validated['until'],
        };

        $request->user()->update([
            'do_not_disturb_until' => $dndUntil,
        ]);

        return response()->json([
            'message' => 'Do Not Disturb enabled',
            'do_not_disturb_until' => $dndUntil,
        ]);
    }

    /**
     * Disable Do Not Disturb mode
     */
    public function disableDnd(Request $request): JsonResponse
    {
        $request->user()->update([
            'do_not_disturb_until' => null,
        ]);

        return response()->json([
            'message' => 'Do Not Disturb disabled',
        ]);
    }
}
