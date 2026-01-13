<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    /**
     * Determine if the user can view the notification
     */
    public function view(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    /**
     * Determine if the user can update the notification
     */
    public function update(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    /**
     * Determine if the user can delete the notification
     */
    public function delete(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }
}
