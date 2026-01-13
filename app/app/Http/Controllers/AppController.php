<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Workspace;
use App\Services\AppTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppController extends Controller
{
    protected AppTokenService $tokenService;

    public function __construct(AppTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Display a listing of apps for a workspace.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('viewAny', [App::class, $workspace]);

        $apps = $workspace->apps()->with(['creator', 'defaultConversation'])->get();

        return response()->json(['apps' => $apps]);
    }

    /**
     * Store a newly created app.
     */
    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('create', [App::class, $workspace]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:bot,webhook,slash',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|in:chat:write,channels:read,channels:write:joined',
            'callback_url' => 'nullable|url',
            'default_conversation_id' => 'nullable|exists:conversations,id',
        ]);

        $result = $this->tokenService->createApp($workspace, $request->user(), $validated);

        return response()->json([
            'app' => $result['app'],
            'credentials' => $result['credentials'], // Shown only once
        ], 201);
    }

    /**
     * Display the specified app.
     */
    public function show(App $app): JsonResponse
    {
        Gate::authorize('view', $app);

        $app->load(['creator', 'defaultConversation']);

        return response()->json(['app' => $app]);
    }

    /**
     * Update the specified app.
     */
    public function update(Request $request, App $app): JsonResponse
    {
        Gate::authorize('update', $app);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'scopes' => 'sometimes|array',
            'scopes.*' => 'string|in:chat:write,channels:read,channels:write:joined',
            'callback_url' => 'nullable|url',
            'default_conversation_id' => 'nullable|exists:conversations,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $app->update($validated);

        return response()->json(['app' => $app]);
    }

    /**
     * Remove the specified app.
     */
    public function destroy(App $app): JsonResponse
    {
        Gate::authorize('delete', $app);

        $app->delete();

        return response()->json(['message' => 'App deleted successfully']);
    }

    /**
     * Regenerate token for an app.
     */
    public function regenerateToken(App $app): JsonResponse
    {
        Gate::authorize('update', $app);

        $newToken = $this->tokenService->regenerateToken($app);

        return response()->json([
            'message' => 'Token regenerated successfully',
            'token' => $newToken, // Shown only once
        ]);
    }
}
