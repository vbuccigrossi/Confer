<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Skip session handling for API requests that use Bearer token authentication.
 *
 * This middleware prevents the creation of database sessions for mobile app
 * requests that authenticate via Sanctum tokens rather than cookies/sessions.
 */
class SkipSessionForTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is an API request with a Bearer token
        if ($this->isTokenAuthenticatedApiRequest($request)) {
            // Disable session for this request by setting driver to array (in-memory)
            config(["session.driver" => "array"]);
        }

        return $next($request);
    }

    /**
     * Determine if this is an API request using Bearer token authentication.
     */
    protected function isTokenAuthenticatedApiRequest(Request $request): bool
    {
        // Must be an API route
        if (!$request->is("api/*")) {
            return false;
        }

        // Must have a Bearer token in the Authorization header
        $authHeader = $request->header("Authorization", "");
        if (!str_starts_with($authHeader, "Bearer ")) {
            return false;
        }

        // Must NOT have a session cookie (indicating this is not a browser session)
        $sessionCookie = config("session.cookie", "laravel_session");
        if ($request->hasCookie($sessionCookie)) {
            return false;
        }

        return true;
    }
}
