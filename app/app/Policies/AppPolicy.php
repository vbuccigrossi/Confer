<?php

namespace App\Policies;

use App\Models\App;
use App\Models\User;
use App\Models\Workspace;

class AppPolicy
{
    /**
     * Determine whether the user can view any apps.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        // User must be a member of the workspace
        return $user->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    /**
     * Determine whether the user can view the app.
     */
    public function view(User $user, App $app): bool
    {
        // User must be a member of the app's workspace
        return $user->workspaces()->where('workspaces.id', $app->workspace_id)->exists();
    }

    /**
     * Determine whether the user can create apps.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        // User must be workspace admin
        return $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Determine whether the user can update the app.
     */
    public function update(User $user, App $app): bool
    {
        // User must be workspace admin
        return $user->workspaces()
            ->where('workspaces.id', $app->workspace_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Determine whether the user can delete the app.
     */
    public function delete(User $user, App $app): bool
    {
        // User must be workspace admin
        return $user->workspaces()
            ->where('workspaces.id', $app->workspace_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
