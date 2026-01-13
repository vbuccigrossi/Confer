<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationMemberController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\IncomingWebhookController;
use App\Http\Controllers\SlashCommandController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminMembersController;
use App\Http\Controllers\Admin\AdminAuditLogsController;
use App\Http\Controllers\Admin\AdminOutboxController;
use App\Http\Controllers\Admin\AdminRetentionController;
use App\Http\Controllers\Admin\AdminInvitesController;
use App\Http\Controllers\Admin\AdminBotsController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\Webhooks\GitLabWebhookController;
use App\Http\Controllers\Internal\InternalReminderController;
use App\Http\Controllers\Internal\InternalPollController;

// Health check endpoints (no auth required)
Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

// TUI update endpoints (no auth required)
Route::get('/updates/tui/version', [UpdateController::class, 'checkTuiVersion'])->name('updates.tui.version');
Route::get('/updates/tui/download', [UpdateController::class, 'downloadTui'])->name('updates.tui.download');

// Metrics endpoint (env-gated, optional basic auth)
Route::get('/metrics', [MetricsController::class, 'index'])->name('metrics');

// Mobile Authentication Routes (public with rate limiting)
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
});

// Authenticated mobile endpoints
Route::prefix('auth')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    Route::get('/profile', [AuthController::class, 'profile'])->name('auth.profile');
    Route::post('/heartbeat', [AuthController::class, 'heartbeat'])->name('auth.heartbeat');
    Route::get('/sessions', [AuthController::class, 'sessions'])->name('auth.sessions');
    Route::delete('/sessions/{tokenId}', [AuthController::class, 'revokeSession'])->name('auth.revoke-session');
});

// User Profile & Management Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('users')->group(function () {
    Route::patch('/profile', [UserController::class, 'updateProfile'])->name('users.update-profile');
    Route::patch('/password', [UserController::class, 'updatePassword'])->name('users.update-password');
    Route::post('/avatar', [UserController::class, 'uploadAvatar'])->name('users.upload-avatar')->middleware('throttle:file-upload');
    Route::delete('/avatar', [UserController::class, 'deleteAvatar'])->name('users.delete-avatar');
    Route::get('/search', [UserController::class, 'search'])->name('users.search');
    Route::get('/unread-counts', [UserController::class, 'unreadCounts'])->name('users.unread-counts');

    // Notification settings - must come before /{userId} catch-all
    Route::get('/notification-settings', [NotificationPreferenceController::class, 'getGlobalSettings'])->name('users.notification-settings');
    Route::put('/notification-settings', [NotificationPreferenceController::class, 'updateGlobalSettings'])->name('users.notification-settings.update');
    Route::post('/dnd', [NotificationPreferenceController::class, 'enableDnd'])->name('users.dnd.enable');
    Route::delete('/dnd', [NotificationPreferenceController::class, 'disableDnd'])->name('users.dnd.disable');

    // This catch-all must be last
    Route::get('/{userId}', [UserController::class, 'show'])->name('users.show');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public invite routes
Route::get('/invites/{token}', [InviteController::class, 'show'])->name('invites.show');

// Protected routes with rate limiting
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Workspace routes
    Route::apiResource('workspaces', WorkspaceController::class);
    Route::get('/workspaces/{workspace}/members', [WorkspaceController::class, 'members'])->name('workspaces.members');

    // Invite routes
    Route::post('/workspaces/{workspace}/invites', [InviteController::class, 'store'])->name('invites.store');
    Route::post('/invites/accept', [InviteController::class, 'accept'])->name('invites.accept');
    Route::delete('/workspaces/{workspace}/invites/{invite}', [InviteController::class, 'destroy'])->name('invites.destroy');
});

// Conversation routes with rate limiting
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('/conversations/discover', [ConversationController::class, 'discover'])->name('conversations.discover');
    Route::get('/conversations/self', [ConversationController::class, 'self'])->name('conversations.self');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::put('/conversations/{conversation}', [ConversationController::class, 'update'])->name('conversations.update');
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');
    Route::post('/conversations/{conversation}/archive', [ConversationController::class, 'archive'])->name('conversations.archive');
    Route::post('/conversations/{conversation}/unarchive', [ConversationController::class, 'unarchive'])->name('conversations.unarchive');

    // Conversation member routes
    Route::get('/conversations/{conversation}/members/search', [ConversationMemberController::class, 'search'])->name('conversation-members.search');
    Route::post('/conversations/{conversation}/members', [ConversationMemberController::class, 'store'])->name('conversation-members.store');
    Route::delete('/conversations/{conversation}/members/{user}', [ConversationMemberController::class, 'destroy'])->name('conversation-members.destroy');
    Route::post('/conversations/{conversation}/leave', [ConversationMemberController::class, 'leave'])->name('conversation-members.leave');

    // Typing indicator routes
    Route::post('/conversations/{conversation}/typing', [ConversationController::class, 'typing'])->name('conversations.typing');
    Route::get('/conversations/{conversation}/typing', [ConversationController::class, 'getTyping'])->name('conversations.typing.get');

    // Message routes
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/{message}/replies', [MessageController::class, 'replies'])->name('messages.replies');
    Route::patch('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
    Route::post('/messages/{message}/read', [MessageController::class, 'markAsRead'])->name('messages.read');

    // Reaction routes
    Route::post('/messages/{message}/reactions', [ReactionController::class, 'store'])->name('reactions.store');
    Route::delete('/messages/{message}/reactions/{emoji}', [ReactionController::class, 'destroy'])->name('reactions.destroy');
    Route::delete('/reactions/{reaction}', [ReactionController::class, 'destroyById'])->name('reactions.destroy-by-id');

    // File routes
    Route::post('/files', [FileController::class, 'store'])->middleware('throttle:file-upload')->name('files.store');
    Route::delete('/files/{attachment}', [FileController::class, 'destroy'])->name('files.destroy');

    // Search routes - stricter rate limiting for expensive full-text search
    Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:30,1')->name('search');

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Notification preference routes
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'index'])->name('notification-preferences.index');
    Route::get('/conversations/{conversation}/notifications', [NotificationPreferenceController::class, 'show'])->name('notification-preferences.show');
    Route::put('/conversations/{conversation}/notifications', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
    Route::post('/conversations/{conversation}/mute', [NotificationPreferenceController::class, 'mute'])->name('notification-preferences.mute');
    Route::delete('/conversations/{conversation}/mute', [NotificationPreferenceController::class, 'unmute'])->name('notification-preferences.unmute');

    // Device token routes for push notifications
    Route::post('/device-tokens', [DeviceTokenController::class, 'store'])->name('device-tokens.store');
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy'])->name('device-tokens.destroy');

    // Analytics routes
    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard'])->name('analytics.dashboard');
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->name('analytics.overview');
    Route::post('/analytics/track', [AnalyticsController::class, 'trackEvent'])->name('analytics.track');

    // Status routes
    Route::get('/status', [StatusController::class, 'show'])->name('status.show');
    Route::put('/status', [StatusController::class, 'update'])->name('status.update');
    Route::delete('/status', [StatusController::class, 'clear'])->name('status.clear');
    Route::get('/status/presets', [StatusController::class, 'presets'])->name('status.presets');
    Route::post('/status/dnd', [StatusController::class, 'enableDnd'])->name('status.dnd.enable');
    Route::delete('/status/dnd', [StatusController::class, 'disableDnd'])->name('status.dnd.disable');
});

// Public file routes (use signed URLs for auth)
Route::get('/files/{attachment}', [FileController::class, 'show'])->name('files.show');
Route::get('/files/{attachment}/thumbnail', [FileController::class, 'thumbnail'])->name('files.thumbnail');

// App Integration routes

// Incoming webhooks (public with token auth)
Route::post('/hooks/{token}', [IncomingWebhookController::class, 'handle'])->name('hooks.handle');

// Slash commands (user auth required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/commands/{command}', [SlashCommandController::class, 'handle'])->name('commands.handle');

    // App management
    Route::get('/workspaces/{workspace}/apps', [AppController::class, 'index'])->name('workspaces.apps.index');
    Route::post('/workspaces/{workspace}/apps', [AppController::class, 'store'])->name('workspaces.apps.store');
    Route::get('/apps/{app}', [AppController::class, 'show'])->name('apps.show');
    Route::put('/apps/{app}', [AppController::class, 'update'])->name('apps.update');
    Route::delete('/apps/{app}', [AppController::class, 'destroy'])->name('apps.destroy');
    Route::post('/apps/{app}/regenerate-token', [AppController::class, 'regenerateToken'])->name('apps.regenerate-token');
});

// Admin routes
Route::prefix('admin/workspaces/{workspace}')->middleware(['auth:sanctum', 'admin_only'])->group(function () {
    // Overview
    Route::get('/overview', [AdminOverviewController::class, 'index'])->name('admin.overview');

    // Settings
    Route::get('/settings', [AdminSettingsController::class, 'show'])->name('admin.settings.show');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');

    // Members
    Route::get('/members', [AdminMembersController::class, 'index'])->name('admin.members.index');
    Route::patch('/members/{user}', [AdminMembersController::class, 'update'])->name('admin.members.update');
    Route::post('/members/{user}/reset-password', [AdminMembersController::class, 'resetPassword'])->name('admin.members.reset-password');
    Route::delete('/members/{user}', [AdminMembersController::class, 'destroy'])->name('admin.members.destroy');

    // Audit Logs
    Route::get('/audit-logs', [AdminAuditLogsController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('/audit-logs/export', [AdminAuditLogsController::class, 'export'])->name('admin.audit-logs.export');

    // Outbox
    Route::get('/outbox', [AdminOutboxController::class, 'index'])->name('admin.outbox.index');

    // Retention
    Route::post('/retention/preview', [AdminRetentionController::class, 'preview'])->name('admin.retention.preview');
    Route::post('/retention/execute', [AdminRetentionController::class, 'execute'])->name('admin.retention.execute');

    // Invites
    Route::get('/invites', [AdminInvitesController::class, 'index'])->name('admin.invites.index');
    Route::post('/invites', [AdminInvitesController::class, 'store'])->name('admin.invites.store');
    Route::delete('/invites/{invite}', [AdminInvitesController::class, 'destroy'])->name('admin.invites.destroy');
    Route::post('/invites/{invite}/regenerate', [AdminInvitesController::class, 'regenerate'])->name('admin.invites.regenerate');

    // Bots
    Route::get('/bots', [AdminBotsController::class, 'index'])->name('admin.bots.index');
    Route::get('/bots/available', [AdminBotsController::class, 'available'])->name('admin.bots.available');
    Route::post('/bots/install', [AdminBotsController::class, 'install'])->name('admin.bots.install');
    Route::post('/bots/install-from-manifest', [AdminBotsController::class, 'installFromManifest'])->name('admin.bots.install-from-manifest');
    Route::patch('/bots/{installation}', [AdminBotsController::class, 'update'])->name('admin.bots.update');
    Route::delete('/bots/{installation}', [AdminBotsController::class, 'uninstall'])->name('admin.bots.uninstall');
    Route::get('/bots/{installation}/tokens', [AdminBotsController::class, 'listTokens'])->name('admin.bots.tokens.index');
    Route::post('/bots/{installation}/tokens', [AdminBotsController::class, 'generateToken'])->name('admin.bots.tokens.store');
    Route::delete('/bots/{installation}/tokens/{token}', [AdminBotsController::class, 'revokeToken'])->name('admin.bots.tokens.destroy');
});


// Bot API endpoints (bot authentication)
use App\Http\Controllers\Api\BotApiController;

Route::prefix('bot')->middleware(['auth.bot', 'throttle:api'])->group(function () {
    Route::post('/messages', [BotApiController::class, 'sendMessage'])->name('bot.messages.send');
    Route::get('/conversations/{id}', [BotApiController::class, 'getConversation'])->name('bot.conversations.show');
});

// External service webhooks (no auth - uses tokens/secrets in payload)
Route::prefix('webhooks')->group(function () {
    // GitLab webhooks - URL: /api/webhooks/gitlab/{installationId}
    Route::post('/gitlab/{installationId}', [GitLabWebhookController::class, 'handle'])
        ->name('webhooks.gitlab');
});

// Internal bot API endpoints (no auth - called internally by bot servers)
// These routes should only be accessible from within the Docker network
Route::prefix('internal')->group(function () {
    // Reminder bot endpoints
    Route::prefix('reminders')->group(function () {
        Route::post('/create', [InternalReminderController::class, 'create'])->name('internal.reminders.create');
        Route::post('/list', [InternalReminderController::class, 'list'])->name('internal.reminders.list');
        Route::post('/delete', [InternalReminderController::class, 'delete'])->name('internal.reminders.delete');
    });

    // Poll bot endpoints
    Route::prefix('polls')->group(function () {
        Route::post('/create', [InternalPollController::class, 'create'])->name('internal.polls.create');
        Route::post('/vote', [InternalPollController::class, 'vote'])->name('internal.polls.vote');
        Route::post('/results', [InternalPollController::class, 'results'])->name('internal.polls.results');
        Route::post('/close', [InternalPollController::class, 'close'])->name('internal.polls.close');
    });
});
