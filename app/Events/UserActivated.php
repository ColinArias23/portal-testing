<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UserActivated implements ShouldBroadcast
{
    public function __construct(
        public int $userId,
        public int $employeeId
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('pending-users');
    }

    public function broadcastAs(): string
    {
        return 'user.activated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'     => $this->userId,
            'employee_id' => $this->employeeId,
        ];
    }
}