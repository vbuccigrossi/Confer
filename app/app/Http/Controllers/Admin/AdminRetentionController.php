<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PurgeExpiredMessagesJob;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin retention controller - message retention management
 */
class AdminRetentionController extends Controller
{
    /**
     * Preview retention purge (dry run)
     */
    public function preview(Request $request, Workspace $workspace): JsonResponse
    {
        // Run dry-run purge job synchronously
        $job = new PurgeExpiredMessagesJob($workspace, dryRun: true, actorUserId: $request->user()->id);
        $result = $job->handle(app(\App\Services\AuditLogService::class), app(\App\Services\StorageService::class));

        return response()->json([
            'dry_run' => true,
            'retention_days' => $workspace->message_retention_days,
            'total_messages' => $result['total_messages'],
            'by_conversation' => $result['by_conversation'],
        ]);
    }

    /**
     * Execute retention purge
     */
    public function execute(Request $request, Workspace $workspace): JsonResponse
    {
        // Queue the purge job
        $job = PurgeExpiredMessagesJob::dispatch($workspace, dryRun: false, actorUserId: $request->user()->id);

        return response()->json([
            'message' => 'Retention purge job queued successfully',
            'job_id' => $job->id ?? null,
        ], 202);
    }
}
