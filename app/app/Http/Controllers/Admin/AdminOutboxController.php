<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutboxEvent;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin outbox controller - read-only view of outbox events
 */
class AdminOutboxController extends Controller
{
    /**
     * List outbox events with filters
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $query = OutboxEvent::where('workspace_id', $workspace->id)
            ->with('app:id,name,type');

        // Filter by status
        if ($request->has('status')) {
            $query->where('delivery_status', $request->input('status'));
        }

        // Order by newest first
        $query->orderBy('created_at', 'desc');

        // Paginate
        $limit = min($request->input('limit', 50), 200);
        $events = $query->paginate($limit);

        return response()->json([
            'data' => $events->items(),
            'next_cursor' => $events->nextPageUrl(),
            'prev_cursor' => $events->previousPageUrl(),
            'total' => $events->total(),
        ]);
    }
}
