<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddMemberRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationMemberController extends Controller
{
    public function store(AddMemberRequest $request, Conversation $conversation): JsonResponse
    {
        $user = User::findOrFail($request->user_id);

        // Check if already a member
        if ($conversation->isMember($user)) {
            return response()->json(['message' => 'User is already a member'], 422);
        }

        $role = $request->role ?? 'member';
        $conversation->addMember($user, $role);

        return response()->json($conversation->load(['members.user']), 201);
    }

    public function destroy(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        Gate::authorize('removeMembers', $conversation);

        if (!$conversation->isMember($user)) {
            return response()->json(['message' => 'User is not a member'], 404);
        }

        // Prevent removing the last owner from a channel
        if ($conversation->isChannel()) {
            $member = $conversation->members()->where('user_id', $user->id)->first();
            if ($member && $member->isOwner()) {
                $ownerCount = $conversation->members()->where('role', 'owner')->count();
                if ($ownerCount <= 1) {
                    return response()->json(['message' => 'Cannot remove the last owner'], 422);
                }
            }
        }

        $conversation->removeMember($user);

        return response()->json(['message' => 'Member removed successfully']);
    }

    public function leave(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('leave', $conversation);

        $conversation->removeMember($request->user());

        return response()->json(['message' => 'Left conversation successfully']);
    }

    /**
     * Search members for autocomplete (e.g., @mentions)
     */
    public function search(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $query = $request->input('q', '');

        $members = $conversation->members()
            ->with('user')
            ->whereHas('user', function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit(10)
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'avatar_url' => $member->user->profile_photo_url,
                ];
            });

        return response()->json($members);
    }
}
