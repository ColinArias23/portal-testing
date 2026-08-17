<?php

namespace App\Support\Leave;

use App\Models\LeaveRequestApprovalStep;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

class LeaveApprovalNotifier
{
    public function actorIdsFor(LeaveRequestApprovalStep $step): Collection
    {
        if ($step->approver_id) {
            return collect([$step->approver_id]);
        }

        if ($step->approver_role) {
            return User::role($step->approver_role)->pluck('id');
        }

        return collect();
    }

    public function notifyActionableStep(LeaveRequestApprovalStep $step, string $title, string $message): void
    {
        $userIds = $this->actorIdsFor($step)->unique()->filter();

        foreach ($userIds as $userId) {
            Notification::create([
                'type'           => 'leave',
                'title'          => $title,
                'message'        => $message,
                'data'           => [
                    'leave_request_id' => $step->leave_request_id,
                    'step_id'          => $step->id,
                    'step_order'       => $step->step_order,
                ],
                'visibility'     => 'user',
                'target_user_id' => $userId,
            ]);
        }
    }

    public function notifyUser(int $userId, string $type, string $title, string $message, array $data = []): void
    {
        Notification::create([
            'type'           => $type,
            'title'          => $title,
            'message'        => $message,
            'data'           => $data,
            'visibility'     => 'user',
            'target_user_id' => $userId,
        ]);
    }
}