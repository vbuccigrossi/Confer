<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\AuditLog;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class IncomingWebhookController extends Controller
{
    /**
     * Handle incoming webhook
     */
    public function handle(Request $request, string $token): JsonResponse
    {
        // Find app by token (using hash verification)
        $app = App::where('is_active', true)->get()->first(function ($a) use ($token) {
            return $a->verifyToken($token);
        });

        if (!$app) {
            return response()->json(['error' => 'Invalid webhook token'], 404);
        }

        if (!$app->isWebhook()) {
            return response()->json(['error' => 'This app is not a webhook'], 403);
        }

        // Rate limiting per app
        $rateLimitKey = "webhook:{$app->id}";
        $maxAttempts = config('apps.rate_limit_per_minute', 30);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        RateLimiter::hit($rateLimitKey, 60);

        // Validate request
        $validated = $request->validate([
            'text' => 'required|string|max:10000',
            'conversation_id' => 'nullable|exists:conversations,id',
            'username' => 'nullable|string|max:255',
            'icon_emoji' => 'nullable|string|max:50',
        ]);

        // Determine conversation
        $conversationId = $validated['conversation_id'] ?? $app->default_conversation_id;

        if (!$conversationId) {
            return response()->json([
                'error' => 'No conversation_id provided and no default configured'
            ], 400);
        }

        // Verify app has access to workspace
        $conversation = \App\Models\Conversation::find($conversationId);
        
        if (!$conversation || $conversation->workspace_id !== $app->workspace_id) {
            return response()->json(['error' => 'Invalid conversation'], 403);
        }

        // Create message as app
        $message = Message::create([
            'conversation_id' => $conversationId,
            'user_id' => $app->created_by, // Posted by app creator
            'body_md' => $validated['text'],
        ]);

        $message->load(['user', 'conversation']);

        // Log audit entry
        AuditLog::logWebhookPosted(
            $app,
            $message,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json($message, 201);
    }
}
