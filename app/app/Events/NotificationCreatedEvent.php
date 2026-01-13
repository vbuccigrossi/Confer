<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Notification $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        $this->notification->load(['actor', 'conversation', 'message']);

        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'actor' => [
                'id' => $this->notification->actor->id,
                'name' => $this->notification->actor->name,
            ],
            'conversation' => [
                'id' => $this->notification->conversation->id,
                'name' => $this->notification->conversation->name,
            ],
            'message_id' => $this->notification->message_id,
            'payload' => $this->notification->payload,
            'created_at' => $this->notification->created_at->toIso8601String(),
        ];
    }
}
