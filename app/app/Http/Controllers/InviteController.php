<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInviteRequest;
use App\Http\Requests\SendInviteRequest;
use App\Models\Invite;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InviteController extends Controller
{
    public function store(SendInviteRequest $request, Workspace $workspace): JsonResponse
    {
        $existingMember = WorkspaceMember::where('workspace_id', $workspace->id)
            ->whereHas('user', fn($q) => $q->where('email', $request->email))
            ->exists();

        if ($existingMember) {
            return response()->json(['message' => 'User is already a member'], 422);
        }

        $existingInvite = Invite::where('workspace_id', $workspace->id)
            ->where('email', $request->email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvite) {
            return response()->json(['message' => 'Invite already sent'], 422);
        }

        $invite = Invite::create([
            'workspace_id' => $workspace->id,
            'email' => $request->email,
            'invited_by' => $request->user()->id,
            'role' => $request->role ?? 'member',
        ]);

        return response()->json($invite->load(['workspace', 'inviter']), 201);
    }

    public function show(string $token): JsonResponse
    {
        $invite = Invite::with(['workspace', 'inviter'])
            ->where('token', $token)
            ->firstOrFail();

        if (!$invite->isValid()) {
            return response()->json([
                'message' => $invite->isAccepted() ? 'Invite already accepted' : 'Invite expired'
            ], 422);
        }

        return response()->json($invite);
    }

    public function accept(AcceptInviteRequest $request): JsonResponse
    {
        $invite = Invite::where('token', $request->token)->firstOrFail();

        if (!$invite->isValid()) {
            return response()->json([
                'message' => $invite->isAccepted() ? 'Invite already accepted' : 'Invite expired'
            ], 422);
        }

        if ($request->user()->email !== $invite->email) {
            return response()->json(['message' => 'Invite email does not match authenticated user'], 403);
        }

        WorkspaceMember::create([
            'workspace_id' => $invite->workspace_id,
            'user_id' => $request->user()->id,
            'role' => $invite->role,
        ]);

        $invite->update(['accepted_at' => now()]);

        return response()->json([
            'message' => 'Invite accepted successfully',
            'workspace' => $invite->workspace->load(['owner', 'members']),
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, Invite $invite): JsonResponse
    {
        Gate::authorize('invite', $workspace);

        if ($invite->workspace_id !== $workspace->id) {
            return response()->json(['message' => 'Invite does not belong to this workspace'], 404);
        }

        $invite->delete();

        return response()->json(['message' => 'Invite revoked successfully']);
    }
}
