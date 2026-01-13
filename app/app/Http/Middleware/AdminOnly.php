<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to enforce admin (owner/admin) access to workspace resources
 */
class AdminOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $workspaceParam  The route parameter name for workspace (default: 'workspace')
     */
    public function handle(Request $request, Closure $next, string $workspaceParam = 'workspace'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Get workspace from route parameter
        $workspace = $request->route($workspaceParam);

        if (!$workspace || !($workspace instanceof Workspace)) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        // Check if user is owner or admin in workspace
        $member = $workspace->members()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin'])
            ->first();

        if (!$member) {
            return response()->json(['error' => 'Forbidden - Admin access required'], 403);
        }

        return $next($request);
    }
}
