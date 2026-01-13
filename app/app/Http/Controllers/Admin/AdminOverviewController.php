<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Admin overview controller - workspace statistics
 */
class AdminOverviewController extends Controller
{
    /**
     * Get workspace overview statistics
     */
    public function index(Workspace $workspace): JsonResponse
    {
        $stats = [
            'total_members' => $workspace->members()->count(),
            'total_conversations' => $workspace->conversations()->count(),
            'total_messages' => DB::table('messages')
                ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
                ->where('conversations.workspace_id', $workspace->id)
                ->count(),
            'storage_used_mb' => $workspace->storage_used_mb,
            'storage_quota_mb' => $workspace->storage_quota_mb,
            'storage_percentage' => $workspace->storage_quota_mb > 0 
                ? round(($workspace->storage_used_mb / $workspace->storage_quota_mb) * 100, 2)
                : 0,
            'retention_days' => $workspace->message_retention_days,
        ];

        return response()->json($stats);
    }
}
