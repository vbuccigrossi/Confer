<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminAuditLogsController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $query = AuditLog::where('workspace_id', $workspace->id)
            ->with(['user:id,name,email', 'app:id,name']);

        if ($request->has('actor')) {
            $query->where('user_id', $request->input('actor'));
        }

        if ($request->has('action')) {
            $query->where('action', 'like', '%' . $request->input('action') . '%');
        }

        if ($request->has('target_type')) {
            $query->where('subject_type', $request->input('target_type'));
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $query->orderBy('created_at', 'desc');

        $limit = min($request->input('limit', 50), 200);
        $logs = $query->paginate($limit);

        return response()->json([
            'data' => $logs->items(),
            'next_cursor' => $logs->nextPageUrl(),
            'prev_cursor' => $logs->previousPageUrl(),
            'total' => $logs->total(),
        ]);
    }

    public function export(Request $request, Workspace $workspace): Response
    {
        $query = AuditLog::where('workspace_id', $workspace->id)
            ->with(['user:id,name,email', 'app:id,name']);

        if ($request->has('actor')) {
            $query->where('user_id', $request->input('actor'));
        }

        if ($request->has('action')) {
            $query->where('action', 'like', '%' . $request->input('action') . '%');
        }

        if ($request->has('target_type')) {
            $query->where('subject_type', $request->input('target_type'));
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $query->orderBy('created_at', 'desc');

        $maxRows = config('admin.audit_log_export_max_rows', 100000);
        $query->limit($maxRows);

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'ID',
                'Timestamp',
                'Actor',
                'Actor Email',
                'App',
                'Action',
                'Subject Type',
                'Subject ID',
                'IP Address',
                'User Agent',
                'Metadata',
            ]);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->toIso8601String(),
                        $log->user?->name ?? 'System',
                        $log->user?->email ?? '',
                        $log->app?->name ?? '',
                        $log->action,
                        $log->subject_type ?? '',
                        $log->subject_id ?? '',
                        $log->ip_address ?? '',
                        $log->user_agent ?? '',
                        json_encode($log->metadata ?? []),
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="audit-logs-' . now()->format('Y-m-d') . '.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
