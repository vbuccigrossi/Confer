<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Update user profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ]);

        $user->update($validated);

        return response()->json([
            'user' => $user->fresh(),
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * Update user password
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Upload user avatar
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'], // 2MB max
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update([
            'avatar_path' => $path,
        ]);

        return response()->json([
            'avatar_url' => Storage::disk('public')->url($path),
            'message' => 'Avatar uploaded successfully',
        ]);
    }

    /**
     * Delete user avatar
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json([
            'message' => 'Avatar deleted successfully',
        ]);
    }

    /**
     * Search users (within current workspace)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $workspaceId = $validated['workspace_id'];
        $query = $validated['query'];
        $limit = $validated['limit'] ?? 10;

        // Verify user has access to this workspace
        $user = $request->user();
        if (!$user->workspaces()->where('workspaces.id', $workspaceId)->exists()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Search users in this workspace OR bots (which aren't workspace members)
        $users = User::where(function ($q) use ($workspaceId, $query) {
                // Either user is in the workspace
                $q->whereHas('workspaces', function ($q2) use ($workspaceId) {
                    $q2->where('workspaces.id', $workspaceId);
                })
                // Or user is a bot (email ends with @bots.local)
                ->orWhere('email', 'LIKE', '%@bots.local');
            })
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get(['id', 'name', 'email', 'profile_photo_path']);

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Get user by ID (within current workspace)
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function show(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        // Verify current user shares a workspace with target user
        $currentUser = $request->user();
        $sharedWorkspace = $currentUser->workspaces()
            ->whereHas('members', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->exists();

        if (!$sharedWorkspace) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'bio', 'avatar_path', 'created_at']),
        ]);
    }

    /**
     * Get unread message counts for all conversations
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function unreadCounts(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversationMembers = $user->conversationMembers()
            ->with('conversation:id,name')
            ->get();

        $unreadCounts = $conversationMembers->map(function ($member) {
            $unreadCount = $member->conversation->messages()
                ->where('user_id', '!=', $member->user_id)
                ->where(function ($q) use ($member) {
                    $q->where('created_at', '>', $member->last_read_at ?? '1970-01-01')
                      ->orWhereNull('id'); // This condition will never be true, just for query structure
                })
                ->count();

            return [
                'conversation_id' => $member->conversation_id,
                'conversation_name' => $member->conversation->name,
                'unread_count' => $unreadCount,
                'last_read_at' => $member->last_read_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'unread_counts' => $unreadCounts,
            'total_unread' => $unreadCounts->sum('unread_count'),
        ]);
    }
}
