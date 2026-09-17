<?php

namespace App\Http\Resources\Leave;

use App\Enums\ApprovalStepStatus;
use App\Enums\ApproverSource;
use App\Enums\LeaveRequestStatus;
use App\Enums\SignatureType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequestApprovalStep;
use App\Models\User;
use App\Policies\LeaveRequestPolicy;
use App\Services\Organization\EmployeeFormatterService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class LeaveRequestResource extends JsonResource
{
    private static array $departmentCache = [];

    private static array $roleHolderCountCache = [];

    public function toArray(Request $request): array
    {
        $canViewConfidentialDetails = $request->user()
            && app(LeaveRequestPolicy::class)->canSeeConfidential($request->user(), $this->resource);

        $department = $this->resolveDepartment();

        $monthlySalary = app(EmployeeFormatterService::class)->resolveMonthlySalary($this->employee);

        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'employee' => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'employment_type' => $this->employee->employment_type,
                'name' => $this->employee->full_name,
                'department_id' => $department?->id,
                'department' => $department?->name,
                'division_id' => $department?->division_id ?? $department?->parent_division_id,
                'division' => $department?->division?->name ?? $department?->parentDivision?->name,
                'designation' => $this->employee->position_title,
                'first_name' => $this->employee->first_name,
                'middle_name' => $this->employee->middle_name,
                'last_name' => $this->employee->last_name,
                'salary' => $monthlySalary,
                'signature_url' => $this->resolveEmployeeSignatureUrl($this->employee),
            ],
            'leave_type' => [
                'id' => $this->leaveType->id,
                'code' => $this->leaveType->code,
                'name' => $this->leaveType->name,
                'color' => $this->leaveType->color,
                'is_confidential' => $this->leaveType->is_confidential,
            ],
            'status' => $this->resolveDisplayStatus(),
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
            'cancellation_reason' => $this->cancellation_reason,
            'returned_at' => $this->returned_at?->toIso8601String(),
            'return_reason' => $this->return_reason,
            'retracted_at' => $this->retracted_at?->toIso8601String(),
            'retraction_reason' => $this->retraction_reason,
            'rescheduled_from_id' => $this->rescheduled_from_id,
            'rescheduled_from' => $this->whenLoaded('rescheduledFrom', fn () => $this->rescheduledFrom ? [
                'id' => $this->rescheduledFrom->id,
                'request_number' => $this->rescheduledFrom->request_number,
                'start_date' => $this->rescheduledFrom->start_date?->toDateString(),
                'end_date' => $this->rescheduledFrom->end_date?->toDateString(),
            ] : null),
            'rescheduled_to' => $this->whenLoaded('rescheduledTo', fn () => optional($this->rescheduledTo->first(), fn ($r) => [
                'id' => $r->id,
                'request_number' => $r->request_number,
                'start_date' => $r->start_date?->toDateString(),
                'end_date' => $r->end_date?->toDateString(),
            ])),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function resolveDisplayStatus(): string
    {
        if ($this->status !== LeaveRequestStatus::Pending) {
            return $this->status->value;
        }

        $currentStep = $this->relationLoaded('approvalSteps')
            ? $this->approvalSteps->firstWhere('step_order', $this->current_step_order)
            : null;

        return $currentStep?->approver_role === 'HR' ? 'for_hr_review' : 'pending_approval';
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

            return $actualActor
                ? [
                    'id' => $actualActor->id,
                    'name' => $actualActor->name,
                    'formal_name' => $actualActor->formal_name,
                    'signature_url' => $this->resolveActorSignatureUrl($actualActor),
                ]
                : null;
        }

        if ($step->approver_source !== ApproverSource::Role) {
            return $step->approver
                ? [
                    'id' => $step->approver->id,
                    'name' => $step->approver->name,
                    'formal_name' => $step->approver->formal_name,
                    'signature_url' => null,
                ]
                : null;
        }

        if (! $step->approver_role) {
            return null;
        }

        $holders = $this->activeHoldersOfRole($step->approver_role);

        return $holders->count() === 1
            ? [
                'id' => $holders->first()->id,
                'name' => $holders->first()->name,
                'formal_name' => $holders->first()->formal_name,
                'signature_url' => null,
            ]
            : null;
    }

    private function resolveActorSignatureUrl(User $actor): ?string
    {
        return $this->resolveEmployeeSignatureUrl($actor->employee);
    }

    private function resolveEmployeeSignatureUrl(?Employee $employee): ?string
    {
        if (! $employee) {
            return null;
        }

        $hasPrimary = $employee->signatures()
            ->where('type', SignatureType::Primary->value)
            ->exists();

        return $hasPrimary
            ? URL::temporarySignedRoute(
                'employees.signatures.preview',
                now()->addMinutes(30),
                ['employee' => $employee->id, 'type' => SignatureType::Primary->value]
            )
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