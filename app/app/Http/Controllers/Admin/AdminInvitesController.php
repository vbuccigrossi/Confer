<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\Workspace;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin invites controller - workspace invite code management
 */
class AdminInvitesController extends Controller
{
    public function __construct(
        private AuditLogService $auditService
    ) {
    }

    /**
     * List workspace invites
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $query = Invite::where('workspace_id', $workspace->id)
            ->with('inviter:id,name,email');

        // Filter by type (single-use email invites vs reusable codes)
        if ($request->has('type')) {
            if ($request->input('type') === 'email') {
                $query->where('is_single_use', true);
            } elseif ($request->input('type') === 'code') {
                $query->where('is_single_use', false);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->input('status') === 'active') {
                $query->where('expires_at', '>', now())
                    ->where(function ($q) {
                        $q->where('is_single_use', false)
                            ->orWhereNull('accepted_at');
                    })
                    ->where(function ($q) {
                        $q->whereNull('max_uses')
                            ->orWhereRaw('use_count < max_uses');
                    });
            } elseif ($request->input('status') === 'expired') {
                $query->where('expires_at', '<=', now());
            } elseif ($request->input('status') === 'used') {
                $query->where(function ($q) {
                    $q->whereNotNull('accepted_at')
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('max_uses')
                                ->whereRaw('use_count >= max_uses');
                        });
                });
            }
        }

        $invites = $query->orderByDesc('created_at')->get()->map(function ($invite) {
            return [
                'id' => $invite->id,
                'email' => $invite->email,
                'invite_code' => $invite->invite_code,
                'role' => $invite->role,
                'is_single_use' => $invite->is_single_use,
                'max_uses' => $invite->max_uses,
                'use_count' => $invite->use_count,
                'expires_at' => $invite->expires_at,
                'accepted_at' => $invite->accepted_at,
                'created_at' => $invite->created_at,
                'is_expired' => $invite->isExpired(),
                'can_be_used' => $invite->canBeUsed(),
                'inviter' => $invite->inviter ? [
                    'id' => $invite->inviter->id,
                    'name' => $invite->inviter->name,
                ] : null,
            ];
        });

        return response()->json($invites);
    }

    /**
     * Create a new invite (email-based or reusable code)
     */
    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['email', 'code'])],
            'email' => ['required_if:type,email', 'nullable', 'email'],
            'role' => ['required', Rule::in(['member', 'admin'])],
            'expires_in' => ['required', 'string', Rule::in(['1h', '24h', '7d', '30d', 'never'])],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        // Calculate expiration
        $expiresAt = match ($validated['expires_in']) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addDays(7),
            '30d' => now()->addDays(30),
            'never' => now()->addYears(100),
        };

        $isSingleUse = $validated['type'] === 'email';

        // For email invites, check if already exists
        if ($isSingleUse && $validated['email']) {
            $existingInvite = Invite::where('workspace_id', $workspace->id)
                ->where('email', $validated['email'])
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($existingInvite) {
                return response()->json([
                    'error' => 'An active invite already exists for this email'
                ], 422);
            }
        }

        $invite = Invite::create([
            'workspace_id' => $workspace->id,
            'email' => $isSingleUse ? $validated['email'] : null,
            'invited_by' => $request->user()->id,
            'role' => $validated['role'],
            'is_single_use' => $isSingleUse,
            'max_uses' => !$isSingleUse ? ($validated['max_uses'] ?? null) : null,
            'use_count' => 0,
            'expires_at' => $expiresAt,
        ]);

        // Log the invite creation
        $description = $isSingleUse
            ? "Created email invite for {$validated['email']}"
            : 'Created reusable invite code';

        $this->auditService->log(
            $workspace,
            'invite.created',
            $request->user(),
            Invite::class,
            $invite->id,
            ['description' => $description, 'role' => $validated['role']]
        );

        return response()->json([
            'message' => 'Invite created successfully',
            'invite' => [
                'id' => $invite->id,
                'email' => $invite->email,
                'invite_code' => $invite->invite_code,
                'token' => $invite->token,
                'role' => $invite->role,
                'is_single_use' => $invite->is_single_use,
                'max_uses' => $invite->max_uses,
                'use_count' => $invite->use_count,
                'expires_at' => $invite->expires_at,
                'can_be_used' => $invite->canBeUsed(),
            ],
        ], 201);
    }

    /**
     * Delete/revoke an invite
     */
    public function destroy(Request $request, Workspace $workspace, Invite $invite): JsonResponse
    {
        if ($invite->workspace_id !== $workspace->id) {
            return response()->json(['error' => 'Invite not found'], 404);
        }

        $inviteInfo = $invite->is_single_use
            ? "email invite for {$invite->email}"
            : "invite code {$invite->invite_code}";

        $inviteId = $invite->id;
        $invite->delete();

        // Log the deletion
        $this->auditService->log(
            $workspace,
            'invite.deleted',
            $request->user(),
            Invite::class,
            $inviteId,
            ['description' => "Deleted {$inviteInfo}"]
        );

        return response()->json(['message' => 'Invite deleted successfully']);
    }

    /**
     * Regenerate an invite code (for reusable codes)
     */
    public function regenerate(Request $request, Workspace $workspace, Invite $invite): JsonResponse
    {
        if ($invite->workspace_id !== $workspace->id) {
            return response()->json(['error' => 'Invite not found'], 404);
        }

        if ($invite->is_single_use) {
            return response()->json(['error' => 'Cannot regenerate single-use invite codes'], 422);
        }

        $oldCode = $invite->invite_code;
        $invite->invite_code = strtoupper(Str::random(8));
        $invite->save();

        // Log the regeneration
        $this->auditService->log(
            $workspace,
            'invite.regenerated',
            $request->user(),
            Invite::class,
            $invite->id,
            ['old_code' => $oldCode, 'new_code' => $invite->invite_code]
        );

        return response()->json([
            'message' => 'Invite code regenerated',
            'invite_code' => $invite->invite_code,
        ]);
    }
}
