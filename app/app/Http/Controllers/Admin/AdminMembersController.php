<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/**
 * Admin members controller - workspace member management
 */
class AdminMembersController extends Controller
{
    public function __construct(
        private AuditLogService $auditService
    ) {
    }

    /**
     * List workspace members
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $query = $workspace->members()
            ->with('user:id,name,email,created_at');

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        $members = $query->get()->map(function ($member) {
            return [
                'id' => $member->user_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role,
                'joined_at' => $member->joined_at,
            ];
        });

        return response()->json($members);
    }

    /**
     * Update member role
     */
    public function update(Request $request, Workspace $workspace, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['owner', 'admin', 'member'])],
        ]);

        $member = $workspace->members()->where('user_id', $user->id)->first();

        if (!$member) {
            return response()->json(['error' => 'User is not a member of this workspace'], 404);
        }

        $oldRole = $member->role;
        $newRole = $validated['role'];

        // Prevent last owner from being demoted
        if ($oldRole === 'owner' && $newRole !== 'owner') {
            $ownerCount = $workspace->members()->where('role', 'owner')->count();
            if ($ownerCount <= 1) {
                return response()->json([
                    'error' => 'Cannot demote the last owner. Promote another member to owner first.'
                ], 422);
            }
        }

        $member->update(['role' => $newRole]);

        // Log the role change
        $this->auditService->logWorkspaceMemberRoleChanged(
            $workspace,
            $request->user(),
            $user,
            $oldRole,
            $newRole
        );

        return response()->json([
            'message' => 'Member role updated successfully',
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $newRole,
            ],
        ]);
    }

    /**
     * Send password reset email to member
     */
    public function resetPassword(Request $request, Workspace $workspace, User $user): JsonResponse
    {
        $member = $workspace->members()->where('user_id', $user->id)->first();

        if (!$member) {
            return response()->json(['error' => 'User is not a member of this workspace'], 404);
        }

        // Send password reset link
        $status = Password::sendResetLink([
            'email' => $user->email
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            // Log the password reset request
            $this->auditService->logWorkspaceMemberPasswordReset(
                $workspace,
                $request->user(),
                $user
            );

            return response()->json([
                'message' => 'Password reset email sent successfully'
            ]);
        }

        return response()->json([
            'error' => 'Failed to send password reset email'
        ], 500);
    }

    /**
     * Remove member from workspace
     */
    public function destroy(Request $request, Workspace $workspace, User $user)
    {
        $member = $workspace->members()->where('user_id', $user->id)->first();

        if (!$member) {
            return back()->with("error", "User is not a member of this workspace");
        }

        // Prevent last owner from being removed
        if ($member->role === 'owner') {
            $ownerCount = $workspace->members()->where('role', 'owner')->count();
            if ($ownerCount <= 1) {
                return back()->with('error', 'Cannot remove the last owner. Transfer ownership first.');
            }
        }

        $member->delete();

        // Log the removal
        $this->auditService->logWorkspaceMemberRemoved(
            $workspace,
            $request->user(),
            $user
        );

        return back()->with('success', 'Member removed successfully');
    }
}
