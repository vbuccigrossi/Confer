<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $status;
    public ?string $statusMessage;
    public ?string $statusEmoji;
    public bool $isDnd;
    public bool $isOnline;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user)
    {
        $this->userId = $user->id;
        $this->status = $user->status;
        $this->statusMessage = $user->status_message;
        $this->statusEmoji = $user->status_emoji;
        $this->isDnd = (bool) $user->is_dnd;
        $this->isOnline = (bool) $user->is_online;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to all workspaces the user is a member of
        $user = User::find($this->userId);
        $channels = [];

        if ($user) {
            foreach ($user->workspaces as $workspace) {
                $channels[] = new PrivateChannel('workspace.' . $workspace->id);
            }
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'user.status.changed';
    }
}
