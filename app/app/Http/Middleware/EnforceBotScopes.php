<?php

namespace App\Http\Middleware;

use App\Models\App;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceBotScopes
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        // Extract bearer token from Authorization header
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Unauthorized - No bearer token provided'], 401);
        }

        // Find app by hashed token
        $app = App::where('is_active', true)->get()->first(function ($app) use ($token) {
            return $app->verifyToken($token);
        });

        if (!$app) {
            return response()->json(['error' => 'Unauthorized - Invalid token'], 401);
        }

        if (!$app->isBot()) {
            return response()->json(['error' => 'Forbidden - Only bot apps can use this endpoint'], 403);
        }

        // Check if app has all required scopes
        foreach ($scopes as $scope) {
            if (!$app->hasScope($scope)) {
                return response()->json([
                    'error' => "Forbidden - Missing required scope: {$scope}"
                ], 403);
            }
        }

        // Attach app to request for downstream use
        $request->attributes->set('app', $app);

        return $next($request);
    }
}
