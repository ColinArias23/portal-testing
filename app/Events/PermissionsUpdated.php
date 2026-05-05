<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PermissionsUpdated implements ShouldBroadcast
{
    public function __construct(public int $userId) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("user.{$this->userId}"),
            new Channel("permissions-updated"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'permissions.updated';
    }
}