<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Message $message
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [FcmChannel::class];
    }

    /**
     * Get the FCM representation of the notification.
     */
    public function toFcm($notifiable): FcmMessage
    {
        $sender = $this->message->user;
        $conversation = $this->message->conversation;

        // Get conversation display name
        $conversationName = $conversation->name ?: 
            ($conversation->display_name ?? 'Conversation');

        // Truncate message body for notification
        $bodyPreview = substr(strip_tags($this->message->body_md), 0, 100);
        if (strlen($this->message->body_md) > 100) {
            $bodyPreview .= '...';
        }

        return FcmMessage::create()
            ->notification(
                FcmNotification::create()
                    ->title("{$sender->name} in {$conversationName}")
                    ->body($bodyPreview)
            )
            ->data([
                'conversation_id' => (string) $conversation->id,
                'workspace_id' => (string) $conversation->workspace_id,
                'message_id' => (string) $this->message->id,
                'type' => 'new_message',
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
        ];
    }
}
