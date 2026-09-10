<?php

namespace App\Services\Leave;

use App\Enums\ApproverSource;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LeaveApprovalFlowStep;
use App\Models\User;
use RuntimeException;

class LeaveApproverResolver
{
    private const LEVEL_LABELS = [
        'CLUSTER' => 'Cluster Head',
        'DIVISION' => 'Division Head',
        'DIRECTORATE' => 'Directorate Head',
        'OFFICE' => 'Office Head',
    ];

    private const DEPARTMENT_LEVEL_LABELS = [
        'CLUSTER' => 'Cluster Head',
        'DEPARTMENT' => 'Department Head',
        'SECTION' => 'Section Head',
        'UNIT' => 'Unit Head',
    ];

    public function resolve(LeaveApprovalFlowStep $flowStep, Employee $employee): ?User
    {
        return match ($flowStep->approver_source) {
            ApproverSource::ImmediateSupervisor => $this->resolveImmediateSupervisor($employee, $flowStep),
            ApproverSource::DepartmentHead => $this->resolveDepartmentHead($employee, $flowStep),
            ApproverSource::ManagementChain => $this->resolveManagementChain($employee, $flowStep),
            ApproverSource::Role => $this->resolveByRole($flowStep),
            ApproverSource::FixedUser => $this->resolveFixedUser($flowStep),
        };
    }

    public function describeManagementChainLabel(Employee $employee): string
    {
        $headedDivision = Division::where('head_employee_id', $employee->id)->first();

        if ($headedDivision) {
            if (! $headedDivision->parent_id) {
                return 'Top of Org Chart';
            }

            $found = $this->climbForHead($headedDivision->parent, $employee);

            return $found
                ? (self::LEVEL_LABELS[$found['division']->type] ?? 'Division Head')
                : 'Top of Org Chart';
        }

        $headedDepartment = Department::where('head_employee_id', $employee->id)->first();

        if ($headedDepartment) {
            $parentDepartment = $this->climbDepartmentForHead($headedDepartment, $employee);

            if ($parentDepartment) {
                return self::DEPARTMENT_LEVEL_LABELS[$parentDepartment->type] ?? 'Department Head';
            }

            $division = $headedDepartment->division;

            if (! $division) {
                return 'Top of Org Chart';
            }

            $found = $this->climbForHead($division, $employee);

            return $found
                ? (self::LEVEL_LABELS[$found['division']->type] ?? 'Division Head')
                : 'Top of Org Chart';
        }

        return 'Department Head';
    }

    private function climbForHead(Division $division, Employee $employee): ?array
    {
        $current = $division;

        while ($current) {
            $head = $current->head;

            if ($head && $head->id !== $employee->id) {
                return ['division' => $current, 'head' => $head];
            }

            $current = $current->parent;
        }

        return null;
    }

    private function climbDepartmentForHead(Department $department, Employee $employee): ?Department
    {
        $current = $department->parent;

        while ($current) {
            if ($current->head_employee_id && $current->head_employee_id !== $employee->id) {
                return $current;
            }

            $current = $current->parent;
        }

        return null;
    }

    private function resolveImmediateSupervisor(Employee $employee, LeaveApprovalFlowStep $flowStep): ?User
    {
        $supervisor = $employee->parents()->first();

        if (! $supervisor || ! $supervisor->user) {
            $this->failIfRequired($flowStep, 'Employee has no immediate supervisor with a linked user account.');
            return null;
        }

        return $supervisor->user;
    }

    private function resolveDepartmentHead(Employee $employee, LeaveApprovalFlowStep $flowStep): ?User
    {
        $head = $employee->primaryDepartmentAssignment?->effective_head;

        if (! $head) {
            $departmentId = $employee->department_ids[0] ?? null;
            if ($departmentId) {
                $head = Department::find($departmentId)?->head;
            }
        }

        if (! $head || ! $head->user) {
            $this->failIfRequired($flowStep, 'Employee\'s department has no designated head with a linked user account.');
            return null;
        }

        if ($head->id === $employee->id) {
            return null;
        }

        return $head->user;
    }

    private function resolveManagementChain(Employee $employee, LeaveApprovalFlowStep $flowStep): ?User
    {
        $headedDivision = Division::where('head_employee_id', $employee->id)->first();

        if ($headedDivision) {
            if (! $headedDivision->parent_id) {
                return null;
            }

            $directorate = $headedDivision->parent;

            return $this->resolveDivisionHeadUser($directorate, $employee, $flowStep, 'Directorate');
        }

        $headedDepartment = Department::where('head_employee_id', $employee->id)->first();

        if ($headedDepartment) {
            $parentDepartment = $this->climbDepartmentForHead($headedDepartment, $employee);

            if ($parentDepartment) {
                return $this->resolveDepartmentHeadUser($parentDepartment, $employee, $flowStep);
            }

            $division = $headedDepartment->division;

            if (! $division) {
                $this->failIfRequired(
                    $flowStep,
                    "Department '{$headedDepartment->name}' has no division configured to escalate its head's leave to."
                );
                return null;
            }

            return $this->resolveDivisionHeadUser($division, $employee, $flowStep, 'Division');
        }

        return $this->resolveDepartmentHead($employee, $flowStep);
    }

    private function resolveDivisionHeadUser(Division $division, Employee $employee, LeaveApprovalFlowStep $flowStep, string $levelLabel): ?User
    {
        $head = $division->head;

        if (! $head || ! $head->user) {
            $this->failIfRequired(
                $flowStep,
                "{$levelLabel} '{$division->name}' has no designated head with a linked user account."
            );
            return null;
        }

        if ($head->id === $employee->id) {
            return null;
        }

        return $head->user;
    }

    private function resolveDepartmentHeadUser(Department $department, Employee $employee, LeaveApprovalFlowStep $flowStep): ?User
    {
        $head = $department->head;

        if (! $head || ! $head->user) {
            $label = self::DEPARTMENT_LEVEL_LABELS[$department->type] ?? 'Head';
            $this->failIfRequired(
                $flowStep,
                "{$label} of '{$department->name}' has no linked user account."
            );
            return null;
        }

        if ($head->id === $employee->id) {
            return null;
        }

        return $head->user;
    }

    private function resolveByRole(LeaveApprovalFlowStep $flowStep): ?User
    {
        if (! $flowStep->approver_role) {
            throw new RuntimeException("Flow step {$flowStep->id} is source=role but has no approver_role configured.");
        }

        $approver = User::role($flowStep->approver_role)->approved()->orderBy('id')->first();

        if (! $approver) {
            $this->failIfRequired($flowStep, "No active user holds the role '{$flowStep->approver_role}'.");
            return null;
        }

        return $approver;
    }

    private function resolveFixedUser(LeaveApprovalFlowStep $flowStep): ?User
    {
        if (! $flowStep->approver_user_id) {
            throw new RuntimeException("Flow step {$flowStep->id} is source=fixed_user but has no approver_user_id configured.");
        }

        return $flowStep->fixedApprover;
    }

    private function failIfRequired(LeaveApprovalFlowStep $flowStep, string $message): void
    {
        if ($flowStep->is_required) {
            throw new RuntimeException($message);
        }
    }
}