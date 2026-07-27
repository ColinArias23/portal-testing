<?php

namespace App\Events\RBAC;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UserActivated implements ShouldBroadcast
{
    public function __construct(
        public int $userId,
        public int $employeeId
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('pending-users');
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