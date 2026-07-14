<?php

namespace App\Events\Organization;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $employee) {}

    public function broadcastOn(): array
    {
        return [new Channel('employees')];
    }

    public function broadcastAs(): string
    {
        return 'employee.updated';
    }

    public function broadcastWith(): array
    {
        return ['employee' => $this->employee];
    }
}