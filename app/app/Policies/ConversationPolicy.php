<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        // Public channels can be viewed by anyone in workspace
        if ($conversation->isPublic()) {
            return true;
        }

        // Private conversations require membership
        return $conversation->isMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Conversation $conversation): bool
    {
        $member = $conversation->members()->where('user_id', $user->id)->first();
        return $member && $member->canManageMembers();
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        // Only creator can delete DMs/group DMs
        if ($conversation->isDM() || $conversation->isGroupDM()) {
            return $conversation->created_by === $user->id;
        }

        // Channel owners can delete channels
        $member = $conversation->members()->where('user_id', $user->id)->first();
        return $member && $member->isOwner();
    }

    public function archive(User $user, Conversation $conversation): bool
    {
        $member = $conversation->members()->where('user_id', $user->id)->first();
        return $member && $member->canManageMembers();
    }

    public function join(User $user, Conversation $conversation): bool
    {
        // Can only join public channels
        if (!$conversation->isPublic()) {
            return false;
        }

        // Cannot join if already a member
        if ($conversation->isMember($user)) {
            return false;
        }

        return true;
    }

    public function addMembers(User $user, Conversation $conversation): bool
    {
        // For group DMs, any member can add
        if ($conversation->isGroupDM()) {
            return $conversation->isMember($user);
        }

        // For channels, only owners/admins can add
        if ($conversation->isChannel()) {
            $member = $conversation->members()->where('user_id', $user->id)->first();
            return $member && $member->canManageMembers();
        }

        // Cannot add members to 1:1 DMs
        return false;
    }

    public function removeMembers(User $user, Conversation $conversation): bool
    {
        $member = $conversation->members()->where('user_id', $user->id)->first();
        return $member && $member->canManageMembers();
    }

    public function leave(User $user, Conversation $conversation): bool
    {
        // Must be a member to leave
        if (!$conversation->isMember($user)) {
            return false;
        }

        // Cannot leave 1:1 DMs (they can only be archived/muted)
        if ($conversation->isDM()) {
            return false;
        }

        return true;
    }
}
