<?php

namespace App\Http\Controllers;

use App\Services\StatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StatusController extends Controller
{
    public function __construct(
        private StatusService $statusService
    ) {}

    /**
     * Get current user's status
     */
    public function show(Request $request): JsonResponse
    {
        $status = $this->statusService->getEffectiveStatus($request->user());

        return response()->json($status);
    }

    /**
     * Update user's status
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,away,dnd,invisible',
            'message' => 'nullable|string|max:100',
            'emoji' => 'nullable|string|max:10',
            'expires_in' => 'nullable|integer|min:1|max:1440', // Max 24 hours in minutes
        ]);

        $expiresAt = null;
        if ($request->expires_in) {
            $expiresAt = now()->addMinutes($request->expires_in);
        }

        $user = $this->statusService->setStatus(
            user: $request->user(),
            status: $request->status,
            message: $request->message,
            emoji: $request->emoji,
            expiresAt: $expiresAt
        );

        return response()->json([
            'status' => $this->statusService->getEffectiveStatus($user),
            'user' => [
                'status' => $user->status,
                'status_message' => $user->status_message,
                'status_emoji' => $user->status_emoji,
            ],
        ]);
    }

    /**
     * Clear user's custom status
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $this->statusService->clearStatus($request->user());

        return response()->json([
            'status' => $this->statusService->getEffectiveStatus($user),
        ]);
    }

    /**
     * Enable Do Not Disturb
     */
    public function enableDnd(Request $request): JsonResponse
    {
        $request->validate([
            'duration' => 'nullable|integer|min:1|max:1440', // Minutes
        ]);

        $until = $request->duration ? now()->addMinutes($request->duration) : null;

        $user = $this->statusService->enableDnd($request->user(), $until);

        return response()->json([
            'status' => $this->statusService->getEffectiveStatus($user),
        ]);
    }

    /**
     * Disable Do Not Disturb
     */
    public function disableDnd(Request $request): JsonResponse
    {
        $user = $this->statusService->disableDnd($request->user());

        return response()->json([
            'status' => $this->statusService->getEffectiveStatus($user),
        ]);
    }

    /**
     * Get available status presets
     */
    public function presets(): JsonResponse
    {
        return response()->json([
            'presets' => $this->statusService->getStatusPresets(),
        ]);
    }
}
