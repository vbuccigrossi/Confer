<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingStoppedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public Conversation $conversation;

    public function __construct(User $user, Conversation $conversation)
    {
        $this->user = $user;
        $this->conversation = $conversation;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversation->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'typing.stopped';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'conversation_id' => $this->conversation->id,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
