<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    /**
     * Get comprehensive analytics dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        // Only allow workspace admins to access analytics
        $workspaceId = session('current_workspace_id');
        if (!$workspaceId) {
            return response()->json(['error' => 'No workspace selected'], 403);
        }

        // Check if user is admin (you can add proper policy later)
        // For now, we'll allow all authenticated users to see their workspace analytics

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now();

        $stats = $this->analyticsService->getDashboardStats($workspaceId, $startDate, $endDate);

        return response()->json($stats);
    }

    /**
     * Get overview stats only
     */
    public function overview(Request $request): JsonResponse
    {
        $workspaceId = session('current_workspace_id');
        if (!$workspaceId) {
            return response()->json(['error' => 'No workspace selected'], 403);
        }

        $stats = $this->analyticsService->getOverviewStats($workspaceId);

        return response()->json($stats);
    }

    /**
     * Track a custom event
     */
    public function trackEvent(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string',
            'entity_type' => 'nullable|string',
            'entity_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        $workspaceId = session('current_workspace_id');

        $this->analyticsService->trackEvent(
            eventType: $request->event_type,
            userId: $request->user()->id,
            workspaceId: $workspaceId,
            entityType: $request->entity_type,
            entityId: $request->entity_id,
            metadata: $request->metadata,
            clientType: 'web'
        );

        return response()->json(['status' => 'tracked']);
    }
}
