<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentWorkspace
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            // Get current workspace from session or user's first workspace
            $workspaceId = session('current_workspace_id');

            if ($workspaceId) {
                $workspace = $request->user()
                    ->workspaces()
                    ->with('owner')
                    ->find($workspaceId);
            } else {
                // Default to first workspace
                $workspace = $request->user()
                    ->workspaces()
                    ->with('owner')
                    ->first();

                if ($workspace) {
                    session(['current_workspace_id' => $workspace->id]);
                }
            }

            // Share workspace data with Inertia
            $request->merge(['currentWorkspace' => $workspace]);
        }

        return $next($request);
    }
}
