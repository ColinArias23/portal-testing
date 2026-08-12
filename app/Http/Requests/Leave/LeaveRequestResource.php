<?php

namespace App\Http\Resources\Leave;

use App\Policies\LeaveRequestPolicy;
use App\Support\Leave\DepartmentResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewConfidentialDetails = $request->user()
            && app(LeaveRequestPolicy::class)->canSeeConfidential($request->user(), $this->resource);

        $department = DepartmentResolver::forEmployee($this->employee);

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
            'approval_steps' => $this->whenLoaded('approvalSteps', fn () => $this->approvalSteps->map(fn ($step) => [
                'step_order' => $step->step_order,
                'label' => $step->label,
                'approver' => $step->approver ? ['id' => $step->approver->id, 'name' => $step->approver->name] : null,
                'status' => $step->status->value,
                'remarks' => $step->remarks,
                'acted_at' => $step->acted_at?->toIso8601String(),
            ])),
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
}