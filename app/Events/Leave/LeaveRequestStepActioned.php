<?php

namespace App\Events\Leave;

use App\Models\LeaveRequestApprovalStep;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveRequestStepActioned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LeaveRequestApprovalStep $step)
    {
    }

    public function broadcastOn(): array
    {
        $leaveRequest = $this->step->leaveRequest;
        $channels = [];

        $employeeUser = $leaveRequest->employee->user;
        if ($employeeUser) {
            $channels[] = new PrivateChannel("App.Models.User.{$employeeUser->id}");
        }

        $nextStep = $leaveRequest->approvalSteps->firstWhere('step_order', $leaveRequest->current_step_order);
        if ($nextStep && $nextStep->approver_id) {
            $channels[] = new PrivateChannel("App.Models.User.{$nextStep->approver_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'leave-request.step-actioned';
    }

    public function broadcastWith(): array
    {
        return [
            'leave_request_id' => $this->step->leave_request_id,
            'step_order' => $this->step->step_order,
            'status' => $this->step->status->value,
            'remarks' => $this->step->remarks,
        ];
    }
}