<?php

use App\Http\Controllers\WorkspaceWebController;
use App\Http\Controllers\ConversationWebController;
use App\Http\Controllers\NotificationPreferencesController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get("/", function () {
    if (auth()->check()) {
        return redirect()->route("web.conversations.index");
    }
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Dashboard redirects to conversations
    Route::get('/dashboard', function () {
        return redirect()->route('web.conversations.index');
    })->name('dashboard');

    // Search route
    Route::get('/search', function () {
        return Inertia::render('Search');
    })->name('web.search');

    // Workspace management routes
    Route::get('/workspaces/create', [WorkspaceWebController::class, 'create'])->name('workspaces.create');
    Route::post('/workspaces', [WorkspaceWebController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/{workspace}/switch', [WorkspaceWebController::class, 'switch'])->name('workspaces.switch');
    Route::get('/workspaces/settings', [WorkspaceWebController::class, 'settings'])->name('workspaces.settings');
    Route::put('/workspaces/{workspace}', [WorkspaceWebController::class, 'update'])->name('workspaces.update');

    // Conversation routes (web UI)
    Route::get('/conversations', [ConversationWebController::class, 'index'])->name('web.conversations.index');
    Route::get('/conversations/create', [ConversationWebController::class, 'create'])->name('web.conversations.create');
    Route::post('/conversations', [ConversationWebController::class, 'store'])->name('web.conversations.store');
    Route::get('/conversations/dm/start', [ConversationWebController::class, 'startDm'])->name('web.conversations.dm.start');
    Route::post('/conversations/dm', [ConversationWebController::class, 'storeDm'])->name('web.conversations.dm.store');

    // Bot DM routes
    Route::get('/conversations/bot-dm/start', [ConversationWebController::class, 'startBotDm'])->name('web.conversations.bot-dm.start');
    Route::post('/conversations/bot-dm', [ConversationWebController::class, 'storeBotDm'])->name('web.conversations.bot-dm.store');

    // Notes to Self route
    Route::get('/conversations/self', [ConversationWebController::class, 'self'])->name('web.conversations.self');

    Route::get('/conversations/{conversation}', [ConversationWebController::class, 'show'])->name('web.conversations.show');
    Route::put('/conversations/{conversation}', [ConversationWebController::class, 'update'])->name('web.conversations.update');
    Route::delete('/conversations/{conversation}', [ConversationWebController::class, 'destroy'])->name('web.conversations.destroy');
    Route::post('/conversations/{conversation}/messages', [ConversationWebController::class, 'storeMessage'])->name('web.conversations.messages.store');

    // Channel member management
    Route::get('/conversations/{conversation}/members/add', [ConversationWebController::class, 'addMembers'])->name('web.conversations.members.add');
    Route::post('/conversations/{conversation}/members', [ConversationWebController::class, 'storeMembers'])->name('web.conversations.members.store');

    // Notification preferences
    Route::put('/user/notification-preferences', [NotificationPreferencesController::class, 'update'])->name('user.notification-preferences.update');

    // Analytics Dashboard
    Route::get('/analytics', function () {
        return Inertia::render('Analytics/Dashboard');
    })->name('analytics.index');
});

// Admin routes
use App\Http\Controllers\Admin\AdminWebController;
use App\Http\Controllers\Admin\EmailSettingsController;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'admin_only',
])->prefix('admin/workspaces/{workspace}')->group(function () {
    Route::get('/overview', [AdminWebController::class, 'overview'])->name('admin.web.overview');
    Route::get('/members', [AdminWebController::class, 'members'])->name('admin.web.members');
    Route::get('/invites', [AdminWebController::class, 'invites'])->name('admin.web.invites');
    Route::get('/bots', [AdminWebController::class, 'bots'])->name('admin.web.bots');
    Route::get('/settings', [AdminWebController::class, 'settings'])->name('admin.web.settings');

    // Email settings
    Route::get('/email', [EmailSettingsController::class, 'index'])->name('admin.web.email');
    Route::put('/email', [EmailSettingsController::class, 'update'])->name('admin.web.email.update');
    Route::post('/email/test', [EmailSettingsController::class, 'test'])->name('admin.web.email.test');
});
