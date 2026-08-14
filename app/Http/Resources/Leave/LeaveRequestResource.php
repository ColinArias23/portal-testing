<?php

namespace App\Http\Resources\Leave;

use App\Enums\ApprovalStepStatus;
use App\Enums\ApproverSource;
use App\Models\Department;
use App\Models\LeaveRequestApprovalStep;
use App\Models\User;
use App\Policies\LeaveRequestPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    private static array $departmentCache = [];

    private static array $roleHolderCountCache = [];

    public function toArray(Request $request): array
    {
        $canViewConfidentialDetails = $request->user()
            && app(LeaveRequestPolicy::class)->canSeeConfidential($request->user(), $this->resource);

        $department = $this->resolveDepartment();

        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'employee' => [
                'id' => $this->employee->id,
                'name' => $this->employee->full_name,
                'department_id' => $department?->id,
                'department' => $department?->name,
                'division_id' => $department?->division_id ?? $department?->parent_division_id,
                'division' => $department?->division?->name ?? $department?->parentDivision?->name,
                'designation' => $this->employee->position_title,
            ],
            'leave_type' => [
                'id' => $this->leaveType->id,
                'code' => $this->leaveType->code,
                'name' => $this->leaveType->name,
                'color' => $this->leaveType->color,
                'is_confidential' => $this->leaveType->is_confidential,
            ],
            'status' => $this->status->value,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_half_day' => $this->is_half_day,
            'total_days' => $this->total_days,
            'reason' => $this->reason,
            'details' => $this->when(
                ! $this->is_confidential || $canViewConfidentialDetails,
                $this->details,
                fn () => ['restricted' => true]
            ),
            'current_step_order' => $this->current_step_order,
            'total_steps' => $this->total_steps,
            'approval_steps' => $this->whenLoaded(
                'approvalSteps',
                fn () => $this->approvalSteps->map(fn ($step) => $this->formatApprovalStep($step))
            ),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($doc) => [
                'id' => $doc->id,
                'requirement_key' => $doc->requirement_key,
                'original_filename' => $doc->original_filename,
                'size_bytes' => $doc->size_bytes,
                'uploaded_at' => $doc->created_at->toIso8601String(),
            ])),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function formatApprovalStep(LeaveRequestApprovalStep $step): array
    {
        return [
            'step_order' => $step->step_order,
            'label' => $step->label,
            'approver' => $this->resolveStepApproverDisplay($step),
            'status' => $step->status->value,
            'remarks' => $step->remarks,
            'acted_at' => $step->acted_at?->toIso8601String(),
        ];
    }

    private function resolveStepApproverDisplay(LeaveRequestApprovalStep $step): ?array
    {
        if ($step->status !== ApprovalStepStatus::Pending) {
            $actualActor = $step->actor ?? $step->approver;

            return $actualActor ? ['id' => $actualActor->id, 'name' => $actualActor->name] : null;
        }

        if ($step->approver_source !== ApproverSource::Role) {
            return $step->approver ? ['id' => $step->approver->id, 'name' => $step->approver->name] : null;
        }

        if (! $step->approver_role) {
            return null;
        }

        $holders = $this->activeHoldersOfRole($step->approver_role);

        return $holders->count() === 1
            ? ['id' => $holders->first()->id, 'name' => $holders->first()->name]
            : null;
    }

    private function activeHoldersOfRole(string $role)
    {
        if (! array_key_exists($role, self::$roleHolderCountCache)) {
            self::$roleHolderCountCache[$role] = User::role($role)->approved()->get();
        }

        return self::$roleHolderCountCache[$role];
    }

    private function resolveDepartment(): ?Department
    {
        $department = $this->employee->primaryDepartmentAssignment?->department;

        if ($department) {
            return $department;
        }

        $fallbackId = $this->employee->department_ids[0] ?? null;

        if (! $fallbackId) {
            return null;
        }

        if (! array_key_exists($fallbackId, self::$departmentCache)) {
            self::$departmentCache[$fallbackId] = Department::with(['division', 'parentDivision'])
                ->find($fallbackId);
        }

        return self::$departmentCache[$fallbackId];
    }
}