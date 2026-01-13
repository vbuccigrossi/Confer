<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\UserSession;
use App\Models\User;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStats(int $workspaceId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        return [
            'overview' => $this->getOverviewStats($workspaceId),
            'users' => $this->getUserStats($workspaceId, $startDate, $endDate),
            'messages' => $this->getMessageStats($workspaceId, $startDate, $endDate),
            'conversations' => $this->getConversationStats($workspaceId, $startDate, $endDate),
            'files' => $this->getFileStats($workspaceId, $startDate, $endDate),
            'client_usage' => $this->getClientUsageStats($workspaceId, $startDate, $endDate),
            'activity' => $this->getActivityTimeline($workspaceId, $startDate, $endDate),
            'system' => $this->getSystemStats(),
        ];
    }

    /**
     * Get high-level overview statistics
     */
    public function getOverviewStats(int $workspaceId): array
    {
        return Cache::remember("analytics.overview.{$workspaceId}", 300, function () use ($workspaceId) {
            $totalUsers = User::whereHas('workspaces', function ($query) use ($workspaceId) {
                $query->where('workspaces.id', $workspaceId);
            })->count();

            $totalMessages = Message::whereHas('conversation', function ($query) use ($workspaceId) {
                $query->where('workspace_id', $workspaceId);
            })->count();

            $totalConversations = Conversation::where('workspace_id', $workspaceId)->count();

            $totalFiles = Attachment::whereHas('message.conversation', function ($query) use ($workspaceId) {
                $query->where('workspace_id', $workspaceId);
            })->count();

            // Try to get active users from sessions first
            $activeUsersToday = UserSession::where('workspace_id', $workspaceId)
                ->where('last_activity_at', '>=', Carbon::today())
                ->distinct('user_id')
                ->count('user_id');

            // Fall back to users who sent messages today if no sessions tracked
            if ($activeUsersToday === 0) {
                $activeUsersToday = Message::whereHas('conversation', function ($query) use ($workspaceId) {
                        $query->where('workspace_id', $workspaceId);
                    })
                    ->where('created_at', '>=', Carbon::today())
                    ->distinct('user_id')
                    ->count('user_id');
            }

            return [
                'total_users' => $totalUsers,
                'total_messages' => $totalMessages,
                'total_conversations' => $totalConversations,
                'total_files' => $totalFiles,
                'active_users_today' => $activeUsersToday,
            ];
        });
    }

    /**
     * Get user-related statistics
     */
    public function getUserStats(int $workspaceId, Carbon $startDate, Carbon $endDate): array
    {
        // Active users per day - try sessions first, fall back to messages
        $activeUsersByDay = UserSession::where('workspace_id', $workspaceId)
            ->whereBetween('last_activity_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(last_activity_at) as date'), DB::raw('COUNT(DISTINCT user_id) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fall back to message activity if no sessions
        if ($activeUsersByDay->isEmpty()) {
            $activeUsersByDay = Message::whereHas('conversation', function ($query) use ($workspaceId) {
                    $query->where('workspace_id', $workspaceId);
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(DISTINCT user_id) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        }

        // Average session duration from sessions
        $avgSessionDuration = UserSession::where('workspace_id', $workspaceId)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->whereNotNull('duration_seconds')
            ->where('duration_seconds', '>', 0)
            ->avg('duration_seconds');

        // Most active users
        $mostActiveUsers = Message::whereHas('conversation', function ($query) use ($workspaceId) {
                $query->where('workspace_id', $workspaceId);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('user_id', DB::raw('COUNT(*) as message_count'))
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderByDesc('message_count')
            ->limit(10)
            ->get();

        return [
            'active_users_by_day' => $activeUsersByDay,
            'avg_session_duration' => round($avgSessionDuration ?? 0),
            'most_active_users' => $mostActiveUsers,
        ];
    }

    /**
     * Get message-related statistics
     */
    public function getMessageStats(int $workspaceId, Carbon $startDate, Carbon $endDate): array
    {
        // Messages per day
        $messagesByDay = Message::whereHas('conversation', function ($query) use ($workspaceId) {
                $query->where('workspace_id', $workspaceId);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Total messages in period
        $totalMessages = $messagesByDay->sum('count');

        // Average messages per day
        $days = $startDate->diffInDays($endDate) + 1;
        $avgMessagesPerDay = $days > 0 ? round($totalMessages / $days, 2) : 0;

        // Messages by hour of day
        $messagesByHour = Message::whereHas('conversation', function ($query) use ($workspaceId) {
                $query->where('workspace_id', $workspaceId);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('EXTRACT(HOUR FROM created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return [
            'messages_by_day' => $messagesByDay,
            'total_messages' => $totalMessages,
            'avg_messages_per_day' => $avgMessagesPerDay,
            'messages_by_hour' => $messagesByHour,
        ];
    }

    /**
     * Get conversation-related statistics
     */
    public function getConversationStats(int $workspaceId, Carbon $startDate, Carbon $endDate): array
    {
        // Top channels by message count (public and private channels)
        $topChannels = Conversation::where('workspace_id', $workspaceId)
            ->whereIn('type', [Conversation::TYPE_PUBLIC_CHANNEL, Conversation::TYPE_PRIVATE_CHANNEL])
            ->withCount(['messages' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->orderByDesc('messages_count')
            ->limit(10)
            ->get(['id', 'name', 'type']);

        // DM statistics
        $totalDms = Conversation::where('workspace_id', $workspaceId)
            ->whereIn('type', [Conversation::TYPE_DM, Conversation::TYPE_GROUP_DM])
            ->count();

        $dmMessageCount = Message::whereHas('conversation', function ($query) use ($workspaceId) {
                $query->where('workspace_id', $workspaceId)
                    ->whereIn('type', [Conversation::TYPE_DM, Conversation::TYPE_GROUP_DM]);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Users by DM count (who receives most DMs)
        $usersByDmCount = DB::table('conversation_members as cm')
            ->join('conversations as c', 'cm.conversation_id', '=', 'c.id')
            ->join('users as u', 'cm.user_id', '=', 'u.id')
            ->where('c.workspace_id', $workspaceId)
            ->whereIn('c.type', [Conversation::TYPE_DM, Conversation::TYPE_GROUP_DM])
            ->select('u.id', 'u.name', 'u.email', DB::raw('COUNT(DISTINCT c.id) as dm_count'))
            ->groupBy('u.id', 'u.name', 'u.email')
            ->orderByDesc('dm_count')
            ->limit(10)
            ->get();

        return [
            'top_channels' => $topChannels,
            'total_dms' => $totalDms,
            'dm_message_count' => $dmMessageCount,
            'users_by_dm_count' => $usersByDmCount,
        ];
    }

    /**
     * Get file-related statistics
     */
    public function getFileStats(int $workspaceId, Carbon $startDate, Carbon $endDate): array
    {
        $files = Attachment::whereHas('message.conversation', function ($query) use ($workspaceId) {
                $query->where('workspace_id', $workspaceId);
            })
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalFiles = $files->count();
        $totalSize = $files->sum('size_bytes');

        // Files by type
        $filesByType = Attachment::whereHas('message.conversation', function ($query) use ($workspaceId) {
                $query->where('workspace_id', $workspaceId);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("CASE
                    WHEN mime_type LIKE 'image/%' THEN 'Images'
                    WHEN mime_type LIKE 'video/%' THEN 'Videos'
                    WHEN mime_type LIKE 'application/pdf' THEN 'PDFs'
                    WHEN mime_type LIKE 'application/%' THEN 'Documents'
                    ELSE 'Other'
                END as type"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(size_bytes) as total_size')
            )
            ->groupBy('type')
            ->get();

        return [
            'total_files' => $totalFiles,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'files_by_type' => $filesByType,
        ];
    }

    /**
     * Get client usage statistics (web, mobile, TUI)
     *
     * Derives client type from Sanctum token naming conventions:
     * - Tokens named 'mobile-*' → mobile client
     * - Tokens named 'tui-*' → TUI client
     * - Other tokens → web client
     */
    public function getClientUsageStats(int $workspaceId, Carbon $startDate, Carbon $endDate): array
    {
        // First try to get from UserSession table if populated
        $sessionStats = UserSession::where('workspace_id', $workspaceId)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->select(
                'client_type',
                DB::raw('COUNT(*) as session_count'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                DB::raw('AVG(duration_seconds) as avg_duration')
            )
            ->groupBy('client_type')
            ->get();

        if ($sessionStats->isNotEmpty()) {
            return ['by_client' => $sessionStats];
        }

        // Fall back to deriving client type from Sanctum tokens
        // Get all users in this workspace
        $workspaceUserIds = User::whereHas('workspaces', function ($query) use ($workspaceId) {
            $query->where('workspaces.id', $workspaceId);
        })->pluck('id');

        // Query Sanctum tokens for these users, active in the date range
        $tokens = DB::table('personal_access_tokens')
            ->whereIn('tokenable_id', $workspaceUserIds)
            ->where('tokenable_type', 'App\\Models\\User')
            ->where(function ($query) use ($startDate, $endDate) {
                // Token was used in the date range OR created in the date range
                $query->whereBetween('last_used_at', [$startDate, $endDate])
                    ->orWhereBetween('created_at', [$startDate, $endDate]);
            })
            ->select('tokenable_id', 'name', 'last_used_at', 'created_at')
            ->get();

        // Classify tokens by client type based on name prefix
        $clientData = [
            'web' => ['users' => collect(), 'sessions' => 0],
            'mobile' => ['users' => collect(), 'sessions' => 0],
            'tui' => ['users' => collect(), 'sessions' => 0],
        ];

        foreach ($tokens as $token) {
            $clientType = 'web'; // default

            if (str_starts_with($token->name, 'mobile-')) {
                $clientType = 'mobile';
            } elseif (str_starts_with($token->name, 'tui-')) {
                $clientType = 'tui';
            }

            $clientData[$clientType]['users']->push($token->tokenable_id);
            $clientData[$clientType]['sessions']++;
        }

        // Build stats collection
        $clientStats = collect();

        foreach ($clientData as $clientType => $data) {
            $uniqueUsers = $data['users']->unique()->count();

            // Only include client types that have activity
            if ($uniqueUsers > 0 || $data['sessions'] > 0) {
                $clientStats->push((object)[
                    'client_type' => $clientType,
                    'session_count' => $data['sessions'],
                    'unique_users' => $uniqueUsers,
                    'avg_duration' => 0, // Not available from tokens
                ]);
            }
        }

        // If still empty (no tokens at all), show workspace member count as web
        if ($clientStats->isEmpty()) {
            $memberCount = $workspaceUserIds->count();
            $clientStats->push((object)[
                'client_type' => 'web',
                'session_count' => 0,
                'unique_users' => $memberCount,
                'avg_duration' => 0,
            ]);
        }

        return [
            'by_client' => $clientStats,
        ];
    }

    /**
     * Get activity timeline (messages, files, events per hour/day)
     */
    public function getActivityTimeline(int $workspaceId, Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate);
        $groupBy = $days > 7 ? 'day' : 'hour';

        if ($groupBy === 'day') {
            $messages = Message::whereHas('conversation', function ($query) use ($workspaceId) {
                    $query->where('workspace_id', $workspaceId);
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(DB::raw('DATE(created_at) as period'), DB::raw('COUNT(*) as count'))
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        } else {
            $messages = Message::whereHas('conversation', function ($query) use ($workspaceId) {
                    $query->where('workspace_id', $workspaceId);
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw("DATE_TRUNC('hour', created_at) as period"),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        }

        return [
            'group_by' => $groupBy,
            'activity' => $messages,
        ];
    }

    /**
     * Get system statistics (uptime, performance, etc.)
     */
    public function getSystemStats(): array
    {
        // Calculate system age - try sessions first, then messages, then users
        $systemAge = 'Unknown';

        $firstSession = UserSession::orderBy('started_at')->first();
        if ($firstSession) {
            $systemAge = Carbon::parse($firstSession->started_at)->diffForHumans(null, true) . ' ago';
        } else {
            // Fall back to earliest message
            $firstMessage = Message::orderBy('created_at')->first();
            if ($firstMessage) {
                $systemAge = Carbon::parse($firstMessage->created_at)->diffForHumans(null, true) . ' ago';
            } else {
                // Fall back to earliest user
                $firstUser = User::orderBy('created_at')->first();
                if ($firstUser) {
                    $systemAge = Carbon::parse($firstUser->created_at)->diffForHumans(null, true) . ' ago';
                }
            }
        }

        return [
            'system_age' => $systemAge,
            'database_size' => $this->getDatabaseSize(),
            'cache_enabled' => config('cache.default') !== 'null',
        ];
    }

    /**
     * Get approximate database size
     */
    private function getDatabaseSize(): string
    {
        try {
            $result = DB::select("SELECT pg_size_pretty(pg_database_size(current_database())) as size");
            return $result[0]->size ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Track an analytics event
     */
    public function trackEvent(
        string $eventType,
        ?int $userId = null,
        ?int $workspaceId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null,
        ?string $clientType = null
    ): void {
        AnalyticsEvent::create([
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'client_type' => $clientType,
        ]);
    }

    /**
     * Start a user session
     */
    public function startSession(
        int $userId,
        int $workspaceId,
        string $clientType,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): UserSession {
        return UserSession::create([
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'client_type' => $clientType,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Update session activity
     */
    public function updateSessionActivity(int $sessionId): void
    {
        UserSession::where('id', $sessionId)
            ->update(['last_activity_at' => now()]);
    }

    /**
     * End a user session
     */
    public function endSession(int $sessionId): void
    {
        $session = UserSession::find($sessionId);
        if ($session) {
            $duration = now()->diffInSeconds($session->started_at);
            $session->update([
                'ended_at' => now(),
                'duration_seconds' => $duration,
            ]);
        }
    }
}
