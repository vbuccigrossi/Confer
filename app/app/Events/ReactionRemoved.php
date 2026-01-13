<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionRemoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reactionId;
    public $messageId;
    public $conversationId;

    public function __construct($reactionId, $messageId, $conversationId)
    {
        $this->reactionId = $reactionId;
        $this->messageId = $messageId;
        $this->conversationId = $conversationId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reaction.removed';
    }

    public function broadcastWith(): array
    {
        return [
            'reaction_id' => $this->reactionId,
            'message_id' => $this->messageId,
        ];
    }
}
