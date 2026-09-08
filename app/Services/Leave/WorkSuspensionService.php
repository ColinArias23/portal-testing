<?php

namespace App\Services\Leave;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use App\Models\WorkSuspension;
use Illuminate\Support\Facades\DB;

class WorkSuspensionService
{
    public function declare(array $data, User $actor): WorkSuspension
    {
        return DB::transaction(function () use ($data, $actor) {
            $affected = $this->findAffectedLeaveRequests($data['start_date'], $data['end_date']);

            $suspension = WorkSuspension::create([
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'],
                'affected_leave_requests_count' => $affected->count(),
                'created_by' => $actor->id,
            ]);

            foreach ($affected as $leaveRequest) {
                $this->notifyAffectedEmployee($leaveRequest, $suspension);
            }

            return $suspension;
        });
    }

    public function findAffectedLeaveRequests(string $startDate, string $endDate)
    {
        return LeaveRequest::query()
            ->where('status', LeaveRequestStatus::Approved)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->with(['employee.user', 'leaveType'])
            ->get();
    }

    private function notifyAffectedEmployee(LeaveRequest $leaveRequest, WorkSuspension $suspension): void
    {
        $targetUser = $leaveRequest->employee?->user;

        if (! $targetUser) {
            return;
        }

        Notification::create([
            'type' => 'leave_suspension_conflict',
            'title' => 'Your approved leave overlaps a declared suspension',
            'message' => "Your approved {$leaveRequest->leaveType?->name} ({$leaveRequest->start_date->format('M j, Y')} – {$leaveRequest->end_date->format('M j, Y')}) "
                . "overlaps a declared work suspension ({$suspension->start_date->format('M j, Y')} – {$suspension->end_date->format('M j, Y')}). "
                . 'Please cancel or change the date of this leave request in the portal.',
            'data' => [
                'leave_request_id' => $leaveRequest->id,
                'work_suspension_id' => $suspension->id,
            ],
            'visibility' => 'user',
            'target_user_id' => $targetUser->id,
        ]);
    }
}