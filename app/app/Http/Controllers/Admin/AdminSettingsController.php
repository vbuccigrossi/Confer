<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin settings controller - workspace retention and quota management
 */
class AdminSettingsController extends Controller
{
    public function __construct(
        private AuditLogService $auditService
    ) {
    }

    /**
     * Show workspace settings
     */
    public function show(Workspace $workspace): JsonResponse
    {
        return response()->json([
            'message_retention_days' => $workspace->message_retention_days,
            'storage_quota_mb' => $workspace->storage_quota_mb,
            'storage_used_mb' => $workspace->storage_used_mb,
        ]);
    }

    /**
     * Update workspace settings
     */
    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        $validated = $request->validate([
            'message_retention_days' => 'nullable|integer|min:0|max:3650',
            'storage_quota_mb' => 'nullable|integer|min:100|max:1000000',
        ]);

        // Track changes for audit log
        $changes = [];
        $old = $workspace->only(['message_retention_days', 'storage_quota_mb']);

        $workspace->update($validated);

        foreach ($validated as $key => $value) {
            if ($old[$key] !== $value) {
                $changes[$key] = [
                    'from' => $old[$key],
                    'to' => $value,
                ];
            }
        }

        // Log the change if there were any
        if (!empty($changes)) {
            $this->auditService->logWorkspaceSettingsChanged(
                $workspace,
                $request->user(),
                $changes
            );
        }

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => [
                'message_retention_days' => $workspace->message_retention_days,
                'storage_quota_mb' => $workspace->storage_quota_mb,
                'storage_used_mb' => $workspace->storage_used_mb,
            ],
        ]);
    }
}
