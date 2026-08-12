<?php

namespace App\Events\Leave;

use App\Models\LeaveRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveRequestSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LeaveRequest $leaveRequest)
    {
    }

    public function broadcastOn(): array
    {
        $step = $this->leaveRequest->currentStep();

        if (! $step || ! $step->approver_id) {
            return [];
        }

        return [
            new PrivateChannel("App.Models.User.{$step->approver_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'leave-request.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'request_number' => $this->leaveRequest->request_number,
            'employee_id' => $this->leaveRequest->employee_id,
            'leave_type' => $this->leaveRequest->leaveType->code,
            'is_confidential' => $this->leaveRequest->is_confidential,
        ];
    }
}