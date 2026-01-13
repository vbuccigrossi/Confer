<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttachmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Any authenticated user can list their own attachments
    }

    /**
     * Determine whether the user can view/download the attachment.
     * Users can view if they are:
     * - The uploader
     * - A member of the conversation where the file is attached
     */
    public function view(User $user, Attachment $attachment): bool
    {
        // Uploader can always view
        if ($attachment->uploader_id === $user->id) {
            return true;
        }

        // If attached to a message, check conversation membership
        if ($attachment->message_id) {
            $message = $attachment->message;
            if ($message && $message->conversation) {
                return $message->conversation->isMember($user);
            }
        }

        // Not attached to a message yet - only uploader can view
        return false;
    }

    /**
     * Determine whether the user can create (upload) attachments.
     */
    public function create(User $user): bool
    {
        return true; // Any authenticated user can upload files
    }

    /**
     * Determine whether the user can update the attachment metadata.
     * Only uploader can update (e.g., attach to a different message).
     */
    public function update(User $user, Attachment $attachment): bool
    {
        return $attachment->uploader_id === $user->id;
    }

    /**
     * Determine whether the user can delete the attachment.
     * Users can delete if they are:
     * - The uploader
     * - An admin/owner of the workspace (if attached to a message)
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        // Uploader can always delete
        if ($attachment->uploader_id === $user->id) {
            return true;
        }

        // If attached to a message, check if user is workspace admin/owner
        if ($attachment->message_id) {
            $message = $attachment->message;
            if ($message && $message->conversation) {
                $workspace = $message->conversation->workspace;
                $membership = $workspace->members()->where('user_id', $user->id)->first();

                if ($membership && in_array($membership->role, ['owner', 'admin'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attachment $attachment): bool
    {
        return $attachment->uploader_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attachment $attachment): bool
    {
        // Only workspace owners can force delete
        if ($attachment->message_id) {
            $message = $attachment->message;
            if ($message && $message->conversation) {
                $workspace = $message->conversation->workspace;
                $membership = $workspace->members()->where('user_id', $user->id)->first();

                return $membership && $membership->role === 'owner';
            }
        }

        return $attachment->uploader_id === $user->id;
    }
}
