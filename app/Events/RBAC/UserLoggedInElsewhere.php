<?php

namespace App\Events\RBAC;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UserLoggedInElsewhere implements ShouldBroadcastNow
{
    public function __construct(public int $userId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'logged.in.elsewhere';
    }
}