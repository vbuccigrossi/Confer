<?php

namespace App\Events;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PresenceUserOfflineEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public Workspace $workspace;

    public function __construct(User $user, Workspace $workspace)
    {
        $this->user = $user;
        $this->workspace = $workspace;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.' . $this->workspace->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'presence.user.offline';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'workspace_id' => $this->workspace->id,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
