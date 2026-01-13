<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use App\Models\Conversation;

class MessagePolicy
{
    /**
     * Determine whether the user can view any messages in a conversation.
     */
    public function viewAny(User $user, Conversation $conversation): bool
    {
        // User must be a member of the conversation
        return $conversation->isMember($user);
    }

    /**
     * Determine whether the user can view the message.
     */
    public function view(User $user, Message $message): bool
    {
        // User must be a member of the conversation
        return $message->conversation->isMember($user);
    }

    /**
     * Determine whether the user can create messages in a conversation.
     */
    public function create(User $user, Conversation $conversation): bool
    {
        // User must be a member and conversation must not be archived
        return $conversation->isMember($user) && !$conversation->is_archived;
    }

    /**
     * Determine whether the user can update the message.
     */
    public function update(User $user, Message $message): bool
    {
        // Use the model's isEditable method which checks:
        // - Author within 15 minutes
        // - OR admin/owner any time
        return $message->isEditable($user);
    }

    /**
     * Determine whether the user can delete the message.
     */
    public function delete(User $user, Message $message): bool
    {
        // Same rules as update
        return $message->isDeletable($user);
    }

    /**
     * Determine whether the user can react to the message.
     */
    public function react(User $user, Message $message): bool
    {
        // User must be a member of the conversation
        return $message->conversation->isMember($user);
    }

    /**
     * Determine whether the user can mark the message as read.
     */
    public function markAsRead(User $user, Message $message): bool
    {
        // User must be a member of the conversation
        return $message->conversation->isMember($user);
    }
}
