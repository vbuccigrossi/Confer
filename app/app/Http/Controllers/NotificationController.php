<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * List notifications for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'only_unread' => 'boolean',
            'limit' => 'integer|min:1|max:100',
        ]);

        $query = Notification::where('user_id', $request->user()->id)
            ->with(['actor', 'conversation', 'message'])
            ->orderBy('created_at', 'desc');

        // Filter by read status
        if ($request->boolean('only_unread')) {
            $query->unread();
        }

        // Pagination
        $limit = $request->input('limit', 50);
        $notifications = $query->limit($limit)->get();

        // Get unread count
        $unreadCount = $this->notificationService->getUnreadCount($request->user());

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        Gate::authorize('update', $notification);

        $this->notificationService->markAsRead($notification);

        return response()->json([
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'message' => "Marked {$count} notifications as read",
            'count' => $count,
        ]);
    }
}
