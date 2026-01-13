<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Configure rate limiters
            RateLimiter::for('api', function ($request) {
                return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
            });

            // Auth rate limiter (for login/register endpoints)
            RateLimiter::for('auth', function ($request) {
                return Limit::perMinute(10)->by($request->ip());
            });

            // File upload rate limiter (more restrictive)
            RateLimiter::for('file-upload', function ($request) {
                return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
            });

            // Search rate limiter
            RateLimiter::for('search', function ($request) {
                return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies (for nginx/cloudflare)
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Skip sessions for mobile API requests that use Bearer token auth
        // This must come BEFORE statefulApi() to prevent session creation
        $middleware->api(prepend: [
            \App\Http\Middleware\SkipSessionForTokenAuth::class,
        ]);

        // Enable stateful API (for Sanctum SPA authentication)
        $middleware->statefulApi();

        // Exclude mobile API endpoints from CSRF protection
        $middleware->validateCsrfTokens(except: [
            'api/*',  // Disable CSRF for all API routes (mobile apps use Bearer tokens)
        ]);

        // Register middleware aliases
        $middleware->alias([
            'admin_only' => \App\Http\Middleware\AdminOnly::class,
            'auth.bot' => \App\Http\Middleware\AuthenticateBot::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
