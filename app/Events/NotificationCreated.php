<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NotificationCreated implements ShouldBroadcast
{
    public function __construct(public string $visibility) {}

    public function broadcastOn(): Channel
    {
        return new Channel('notifications');
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return ['visibility' => $this->visibility];
    }
}