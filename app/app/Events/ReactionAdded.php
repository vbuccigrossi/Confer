<?php

namespace App\Events;

use App\Models\Reaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reaction;
    public $conversationId;

    public function __construct(Reaction $reaction)
    {
        $this->reaction = $reaction->load('user');
        $this->conversationId = $reaction->message->conversation_id;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reaction.added';
    }

    public function broadcastWith(): array
    {
        return [
            'reaction' => $this->reaction->toArray(),
        ];
    }
}
