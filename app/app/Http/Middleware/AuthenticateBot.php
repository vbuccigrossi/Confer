<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\BotToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBot
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Bot token is required',
            ], 401);
        }

        // Find the bot token
        $botToken = BotToken::where('token', hash('sha256', $token))
            ->with(['installation.bot', 'installation.workspace'])
            ->first();

        if (!$botToken) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid bot token',
            ], 401);
        }

        // Check if token is expired
        if ($botToken->isExpired()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Bot token has expired',
            ], 401);
        }

        // Check if installation is active
        if (!$botToken->installation->is_active) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Bot is not active in this workspace',
            ], 403);
        }

        // Mark token as used
        $botToken->markAsUsed();

        // Attach bot installation to request
        $request->merge([
            'bot_installation' => $botToken->installation,
            'bot_token' => $botToken,
        ]);

        return $next($request);
    }
}
