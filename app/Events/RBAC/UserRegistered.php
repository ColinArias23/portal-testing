<?php

namespace App\Events\RBAC;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UserRegistered implements ShouldBroadcastNow
{
    public function __construct(
        public int $userId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('pending-users');
    }

    public function broadcastAs(): string
    {
        return 'user.registered';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
        ];
    }
}