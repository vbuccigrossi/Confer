<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\AuditLog;
use App\Models\OutboxEvent;
use App\Services\OutboxDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SlashCommandController extends Controller
{
    protected OutboxDispatcher $dispatcher;

    public function __construct(OutboxDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handle slash command
     */
    public function handle(Request $request, string $command): JsonResponse
    {
        // Find app by command name
        $app = App::where('type', App::TYPE_SLASH)
            ->where('name', $command)
            ->where('is_active', true)
            ->first();

        if (!$app) {
            return response()->json(['error' => "Unknown command: /{$command}"], 404);
        }

        // Verify user has access to workspace
        if ($request->user()->workspaces()->where('workspaces.id', $app->workspace_id)->doesntExist()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Rate limiting per user
        $rateLimitKey = "slash:{$request->user()->id}:{$command}";
        $maxAttempts = config('apps.rate_limit_per_minute', 30);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        RateLimiter::hit($rateLimitKey, 60);

        // Validate request
        $validated = $request->validate([
            'text' => 'required|string',
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        // Verify user has access to conversation
        $conversation = \App\Models\Conversation::find($validated['conversation_id']);
        
        if ($conversation->workspace_id !== $app->workspace_id) {
            return response()->json(['error' => 'Invalid conversation'], 403);
        }

        // Create payload for outbox
        $payload = [
            'command' => $command,
            'text' => $validated['text'],
            'conversation_id' => $validated['conversation_id'],
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'workspace_id' => $app->workspace_id,
        ];

        // Dispatch to outbox (if callback URL configured)
        if ($app->callback_url) {
            $this->dispatcher->dispatch($app, OutboxEvent::EVENT_TYPE_SLASH_COMMAND, $payload);
        }

        // Log audit entry
        AuditLog::logSlashCommandInvoked(
            $app,
            $request->user(),
            $command,
            $payload,
            $request->ip(),
            $request->userAgent()
        );

        // Return ephemeral response
        return response()->json([
            'type' => 'ephemeral',
            'text' => "Command /{$command} received. Processing..."
        ], 200);
    }
}
