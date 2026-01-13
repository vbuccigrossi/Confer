<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

/**
 * Policy for workspace authorization
 */
class WorkspacePolicy
{
    /**
     * Determine if the user can view any workspaces
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the workspace
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists()
            || $workspace->owner_id === $user->id;
    }

    /**
     * Determine if the user can create workspaces
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the workspace
     */
    public function update(User $user, Workspace $workspace): bool
    {
        $member = $workspace->members()->where('user_id', $user->id)->first();
        return $workspace->owner_id === $user->id 
            || ($member && in_array($member->role, ['owner', 'admin']));
    }

    /**
     * Determine if the user can delete the workspace
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    /**
     * Determine if the user can invite members to the workspace
     */
    public function invite(User $user, Workspace $workspace): bool
    {
        $member = $workspace->members()->where('user_id', $user->id)->first();
        return $workspace->owner_id === $user->id 
            || ($member && in_array($member->role, ['owner', 'admin']));
    }

    /**
     * Determine if the user can manage members of the workspace
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        $member = $workspace->members()->where('user_id', $user->id)->first();
        return $workspace->owner_id === $user->id 
            || ($member && in_array($member->role, ['owner', 'admin']));
    }
    /**
     * Determine if the user can view admin panel
     */
    public function viewAdmin(User $user, Workspace $workspace): bool
    {
        $member = $workspace->members()->where('user_id', $user->id)->first();
        return $workspace->owner_id === $user->id
            || ($member && in_array($member->role, ['owner', 'admin']));
    }
}
