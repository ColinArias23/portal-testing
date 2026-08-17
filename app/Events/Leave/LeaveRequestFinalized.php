<?php

namespace App\Events\Leave;

use App\Models\LeaveRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveRequestFinalized implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LeaveRequest $leaveRequest)
    {
    }

    public function broadcastOn(): array
    {
        if (! $this->leaveRequest->employee->user?->id) {
            return [];
        }

        return [
            new PrivateChannel("App.Models.User." . $this->leaveRequest->employee->user?->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'leave-request.finalized';
    }

    public function broadcastWith(): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'request_number' => $this->leaveRequest->request_number,
            'status' => $this->leaveRequest->status->value,
        ];
    }
}