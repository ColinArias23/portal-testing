<?php

namespace App\Services\Organization;

use App\Models\Employee;
use App\Repositories\Shared\EmployeeRepository;
use App\Traits\Concerns\ResolvesAvatarUrl;
use Illuminate\Support\Collection;

class EmployeeFormatterService
{
    use ResolvesAvatarUrl;

    public function __construct(
        private readonly EmployeeRepository $employees,
    ) {
    }

    public function format(Employee $employee, ?Collection $allDepts = null): array
    {
        $allDepts ??= $this->employees->cachedDepartmentsById();

        $departments = collect($employee->department_ids ?? [])
            ->map(fn ($id) => $allDepts->get($id))
            ->filter()
            ->values()
            ->toArray();

        $avatarUrl     = $this->resolveAvatarUrl($employee->getRawOriginal('avatar_url'));
        $assignment    = $employee->primaryAssignment;
        $position      = $assignment?->plantillaPosition;
        $stepIncrement = $assignment?->effectiveStepIncrement();

        $positionInfo = null;
        $employmentType = strtolower(trim($employee->employment_type ?? ''));

        if ($employmentType === 'plantilla') {
            $positionInfo = $position ? [
                'id'             => $position->id,
                'type'           => 'plantilla',
                'item_number'    => $position->item_number,
                'position_title' => $position->position_title,
                'status'         => $position->computed_status,
                'salary_grade'   => $position->salaryGrade ? [
                    'id'             => $position->salaryGrade->id,
                    'salary_grade'   => $position->salaryGrade->salary_grade,
                    'monthly_salary' => $position->salaryGrade->monthly_salary,
                    'annual_salary'  => $position->salaryGrade->annual_salary,
                ] : null,
            ] : null;
        } elseif ($employmentType === 'contract of service' || $employmentType === 'cos') {
            $cosPosition = $employee->cosPosition;
            $positionInfo = $cosPosition ? [
                'id'    => $cosPosition->id,
                'title' => $cosPosition->title,
                'type'  => 'cos',
            ] : null;
        } elseif ($employmentType === 'consultant') {
            $consultantPosition = $employee->consultantPosition;
            $positionInfo = $consultantPosition ? [
                'id'    => $consultantPosition->id,
                'title' => $consultantPosition->title,
                'type'  => 'consultant',
            ] : null;
        }

        $normalizedPositionInfo = $positionInfo ? [
            'type'  => $positionInfo['type'] ?? 'plantilla',
            'id'    => $positionInfo['id'] ?? null,
            'title' => $employmentType === 'plantilla'
                ? ($positionInfo['position_title'] ?? null)
                : ($positionInfo['title'] ?? null),
        ] : null;

        $latestAssessment = $employee->relationLoaded('latestAssessment')
            ? $employee->latestAssessment
            : $employee->latestAssessment()->first();

        $activePlans = $employee->relationLoaded('activeDevelopmentPlans')
            ? $employee->activeDevelopmentPlans
            : $employee->activeDevelopmentPlans()->with('skill')->get();

        $resolvedAnnualSalary  = $this->resolveAnnualSalary($employee, $employmentType, $stepIncrement);
        $resolvedMonthlySalary = $this->resolveMonthlySalary($employee, $employmentType, $stepIncrement);

        $salaryHistory = $employee->relationLoaded('salaryHistories')
            ? $employee->salaryHistories->whereNull('end_date')->sortByDesc('effective_date')->first()
            : $employee->salaryHistories()->whereNull('end_date')->latest('effective_date')->first();

        return [
            'id'                   => $employee->id,
            'employee_number'      => $employee->employee_number,
            'user'                 => $employee->user ? [
                'id'       => $employee->user->id,
                'username' => $employee->user->username,
            ] : null,
            'prefix'               => $employee->prefix,
            'first_name'           => $employee->first_name,
            'middle_name'          => $employee->middle_name,
            'last_name'            => $employee->last_name,
            'suffix'               => $employee->suffix,
            'full_name'            => $employee->full_name,
            'role_position'        => $employee->role_position        ?? [],
            'position_designation' => $employee->position_designation ?? [],
            'position_info'        => $normalizedPositionInfo,
            'title'                => $employee->title                ?? [],
            'employment_type'      => $employee->employment_type,
            'employment_status'    => $employee->employment_status,
            'annual_salary'        => $resolvedAnnualSalary,
            'monthly_salary'       => $resolvedMonthlySalary,
            'salary_override'      => $employee->salary_override,
            'division_id'          => $employee->division_id,
            'division'             => $employee->division?->only(['id', 'name']),
            'department_ids'       => $employee->department_ids       ?? [],
            'departments'          => $departments,
            'avatar_url'           => $avatarUrl,
            'border_color'         => $employee->border_color,
            'aligned'              => $employee->aligned,
            'expanded'             => $employee->expanded,
            'notes'                => $employee->notes,
            'info'                 => $employee->info ? [
                'gender'    => $employee->info->gender,
                'address'   => $employee->info->address,
                'birthdate' => $employee->info->birthdate,
                'contact'   => $employee->info->contact,
            ] : null,
            'parents' => $employee->parents->map(fn ($p) => [
                'id'        => $p->id,
                'full_name' => $p->full_name,
            ])->toArray(),

            'cos_position_id'        => $employee->cos_position_id,
            'consultant_position_id' => $employee->consultant_position_id,

            'salary_history' => $salaryHistory ? [
                'id'             => $salaryHistory->id,
                'gross_salary'   => $salaryHistory->gross_salary,
                'annual_salary'  => $salaryHistory->annual_salary,
                'salary_grade'   => $salaryHistory->salary_grade,
                'step'           => $salaryHistory->step,
                'effective_date' => $salaryHistory->effective_date,
            ] : null,

            'primary_assignment' => $assignment ? [
                'id'                 => $assignment->id,
                'start_date'         => $assignment->start_date,
                'plantilla_position' => $positionInfo,
                'step_increment'     => ($employmentType === 'plantilla' && $stepIncrement) ? [
                    'id'             => $stepIncrement->id,
                    'step'           => $stepIncrement->step,
                    'monthly_salary' => $stepIncrement->monthly_salary,
                    'annual_salary'  => $stepIncrement->annual_salary,
                ] : null,
            ] : ($positionInfo ? [
                'plantilla_position' => $positionInfo,
            ] : null),

            'skill_summary' => $latestAssessment ? [
                'assessment_id'  => $latestAssessment->id,
                'status'         => $latestAssessment->status,
                'average_score'  => $latestAssessment->average_score,
                'gap_count'      => $latestAssessment->gap_count,
                'strength_count' => $latestAssessment->strength_count,
                'submitted_at'   => $latestAssessment->submitted_at,
            ] : null,

            'development_plans' => $activePlans->map(fn ($plan) => [
                'id'    => $plan->id,
                'skill' => $plan->skill ? [
                    'id'       => $plan->skill->id,
                    'name'     => $plan->skill->name,
                    'category' => $plan->skill->category,
                ] : null,
                'goal'                => $plan->goal,
                'status'              => $plan->status,
                'progress_percentage' => $plan->progress_percentage,
                'start_date'          => $plan->start_date,
                'target_date'         => $plan->target_date,
            ])->toArray(),

            'created_at' => $employee->created_at,
            'updated_at' => $employee->updated_at,
        ];
    }

    public function resolveAvatarUrlPublic(?string $path): ?string
    {
        return $this->resolveAvatarUrl($path);
    }

    public function resolveAnnualSalary(Employee $employee, ?string $employmentType = null, $stepIncrement = null): ?string
    {
        $employmentType ??= strtolower(trim($employee->employment_type ?? ''));
        $stepIncrement  ??= $employee->primaryAssignment?->effectiveStepIncrement();

        if ($employmentType === 'plantilla' && ! $employee->salary_override && $stepIncrement) {
            return $stepIncrement->annual_salary;
        }

        return $employee->annual_salary;
    }

    public function resolveMonthlySalary(Employee $employee, ?string $employmentType = null, $stepIncrement = null): ?string
    {
        $employmentType ??= strtolower(trim($employee->employment_type ?? ''));
        $stepIncrement  ??= $employee->primaryAssignment?->effectiveStepIncrement();

        if ($employmentType === 'plantilla' && ! $employee->salary_override && $stepIncrement) {
            return $stepIncrement->monthly_salary;
        }

        if ($employee->monthly_salary === null && $employee->annual_salary !== null) {
            return (string) round((float) $employee->annual_salary / 12, 2);
        }

        return $employee->monthly_salary;
    }

    public function resolveSalarySnapshot(Employee $employee): array
    {
        $employmentType = strtolower(trim($employee->employment_type ?? ''));
        $assignment     = $employee->primaryAssignment;
        $stepIncrement  = $assignment?->effectiveStepIncrement();
        $position       = $assignment?->plantillaPosition;

        $useStepIncrement = $employmentType === 'plantilla' && ! $employee->salary_override && $stepIncrement;

        if ($useStepIncrement) {
            return [
                'gross_salary'  => $stepIncrement->monthly_salary,
                'annual_salary' => $stepIncrement->annual_salary,
                'salary_grade'  => $position?->salaryGrade?->salary_grade,
                'step'          => $stepIncrement->step,
            ];
        }

        $annual  = $employee->annual_salary  !== null ? (float) $employee->annual_salary  : null;
        $monthly = $employee->monthly_salary !== null
            ? (float) $employee->monthly_salary
            : ($annual !== null ? round($annual / 12, 2) : null);

        return [
            'gross_salary'  => $monthly,
            'annual_salary' => $employee->annual_salary,
            'salary_grade'  => $employmentType === 'plantilla' ? $position?->salaryGrade?->salary_grade : null,
            'step'          => ($employmentType === 'plantilla' && $stepIncrement) ? $stepIncrement->step : null,
        ];
    }
}
