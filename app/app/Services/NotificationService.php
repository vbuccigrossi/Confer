<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Check if user should receive notifications
     */
    protected function shouldNotifyUser(User $user, Message $message, string $notificationType = 'all'): bool
    {
        // Check if user has DND enabled
        if ($user->do_not_disturb_until && $user->do_not_disturb_until->isFuture()) {
            return false;
        }

        // Check quiet hours
        if ($user->quiet_hours_start && $user->quiet_hours_end) {
            $now = now()->format('H:i');
            $start = $user->quiet_hours_start;
            $end = $user->quiet_hours_end;

            // Handle quiet hours that span midnight
            if ($start < $end) {
                if ($now >= $start && $now <= $end) {
                    return false;
                }
            } else {
                if ($now >= $start || $now <= $end) {
                    return false;
                }
            }
        }

        // Get conversation-specific preferences
        $preference = NotificationPreference::where('user_id', $user->id)
            ->where('conversation_id', $message->conversation_id)
            ->first();

        // If no specific preference, check default level
        if (!$preference) {
            $defaultLevel = $user->default_notify_level ?? 'all';

            if ($defaultLevel === 'nothing') {
                return false;
            }

            if ($defaultLevel === 'mentions' && $notificationType !== 'mention') {
                return false;
            }

            return true;
        }

        // Use conversation-specific preference
        return $preference->shouldNotify($notificationType);
    }

    /**
     * Check if message contains user's notification keywords
     */
    protected function containsKeywords(string $messageBody, array $keywords): bool
    {
        if (empty($keywords)) {
            return false;
        }

        $messageLower = strtolower($messageBody);

        foreach ($keywords as $keyword) {
            if (stripos($messageLower, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a mention notification
     */
    public function createMentionNotification(Message $message, User $mentionedUser): ?Notification
    {
        // Don't notify if user mentions themselves
        if ($message->user_id === $mentionedUser->id) {
            return null;
        }

        // Check if user should be notified
        if (!$this->shouldNotifyUser($mentionedUser, $message, 'mention')) {
            return null;
        }

        // Check for duplicate
        $existing = Notification::where('user_id', $mentionedUser->id)
            ->where('message_id', $message->id)
            ->where('type', Notification::TYPE_MENTION)
            ->first();

        if ($existing) {
            return $existing;
        }

        $notification = Notification::create([
            'workspace_id' => $message->conversation->workspace_id,
            'user_id' => $mentionedUser->id,
            'type' => Notification::TYPE_MENTION,
            'actor_user_id' => $message->user_id,
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
            'payload' => [
                'message_preview' => substr($message->body_md, 0, 100),
            ],
            'is_read' => false,
        ]);

        // Broadcast notification event
        event(new \App\Events\NotificationCreatedEvent($notification));

        return $notification;
    }

    /**
     * Create a thread reply notification
     */
    public function createThreadReplyNotification(Message $reply, Message $parentMessage): ?Notification
    {
        // Don't notify if user replies to their own message
        if ($parentMessage->user_id === $reply->user_id) {
            return null;
        }

        // Don't notify if parent author is null (deleted user)
        if (!$parentMessage->user_id) {
            return null;
        }

        $parentAuthor = User::find($parentMessage->user_id);
        if (!$parentAuthor) {
            return null;
        }

        // Check if user should be notified
        if (!$this->shouldNotifyUser($parentAuthor, $reply, 'reply')) {
            return null;
        }

        // Check for duplicate
        $existing = Notification::where('user_id', $parentMessage->user_id)
            ->where('message_id', $reply->id)
            ->where('type', Notification::TYPE_THREAD_REPLY)
            ->first();

        if ($existing) {
            return $existing;
        }

        $notification = Notification::create([
            'workspace_id' => $reply->conversation->workspace_id,
            'user_id' => $parentMessage->user_id,
            'type' => Notification::TYPE_THREAD_REPLY,
            'actor_user_id' => $reply->user_id,
            'conversation_id' => $reply->conversation_id,
            'message_id' => $reply->id,
            'payload' => [
                'parent_message_id' => $parentMessage->id,
                'reply_preview' => substr($reply->body_md, 0, 100),
            ],
            'is_read' => false,
        ]);

        // Broadcast notification event
        event(new \App\Events\NotificationCreatedEvent($notification));

        return $notification;
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Notification $notification): bool
    {
        return $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Get unread count for a user
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get unread notifications for a user (for email digest)
     */
    public function getUnreadForDigest(User $user): \Illuminate\Support\Collection
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->with(['actor', 'conversation', 'message'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Send push notifications to conversation members for new messages
     */
    public function sendNewMessagePushNotifications(Message $message): void
    {
        // Get all conversation members except the message sender
        $conversation = $message->conversation;
        $members = $conversation->members()
            ->where('user_id', '!=', $message->user_id)
            ->with('user')
            ->get();

        foreach ($members as $member) {
            if ($member->user) {
                // Check if user should receive notifications for this message
                if (!$this->shouldNotifyUser($member->user, $message, 'all')) {
                    continue;
                }

                // Check for notification keywords
                $keywords = $member->user->notification_keywords ?? [];
                if (!empty($keywords) && $this->containsKeywords($message->body_md, $keywords)) {
                    // Create keyword notification
                    $this->createKeywordNotification($message, $member->user, $keywords);
                }

                // Get notification preference to check push settings
                $preference = NotificationPreference::where('user_id', $member->user->id)
                    ->where('conversation_id', $message->conversation_id)
                    ->first();

                // Determine if we should send push
                $shouldPushMobile = $preference ? $preference->shouldPush('mobile') : true;
                $shouldPushDesktop = $preference ? $preference->shouldPush('desktop') : true;

                // Only send if user has device tokens and push is enabled
                if (($shouldPushMobile || $shouldPushDesktop) && $member->user->deviceTokens()->count() > 0) {
                    // Dispatch notification (will be queued)
                    $member->user->notify(new NewMessageNotification($message));
                }
            }
        }
    }

    /**
     * Create a keyword match notification
     */
    protected function createKeywordNotification(Message $message, User $user, array $keywords): ?Notification
    {
        // Don't notify if user sent the message
        if ($message->user_id === $user->id) {
            return null;
        }

        // Check for duplicate
        $existing = Notification::where('user_id', $user->id)
            ->where('message_id', $message->id)
            ->where('type', Notification::TYPE_KEYWORD)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Find which keyword matched
        $matchedKeyword = null;
        foreach ($keywords as $keyword) {
            if (stripos(strtolower($message->body_md), strtolower($keyword)) !== false) {
                $matchedKeyword = $keyword;
                break;
            }
        }

        $notification = Notification::create([
            'workspace_id' => $message->conversation->workspace_id,
            'user_id' => $user->id,
            'type' => Notification::TYPE_KEYWORD,
            'actor_user_id' => $message->user_id,
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
            'payload' => [
                'keyword' => $matchedKeyword,
                'message_preview' => substr($message->body_md, 0, 100),
            ],
            'is_read' => false,
        ]);

        // Broadcast notification event
        event(new \App\Events\NotificationCreatedEvent($notification));

        return $notification;
    }
}
