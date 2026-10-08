<?php

namespace App\Services\Recruitment\Hiring;

use App\Events\Organization\EmployeeUpdated;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Onboarding;
use App\Models\PlantillaPosition;
use App\Models\PlantillaPostingApplication;
use App\Repositories\Recruitment\Hiring\OnboardingRepository;
use App\Services\Organization\EmployeeFormatterService;
use App\Services\Organization\EmployeeWriteService;
use App\Services\Recruitment\PlantillaPosting\PlantillaService;
use App\Support\Broadcasting\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PlantillaOnboardingService
{
    public const TYPE_NEW       = 'NEW';
    public const TYPE_PROMOTION = 'PROMOTION';

    private const REQUIREMENTS = [
        self::TYPE_NEW       => ['GSIS', 'PAG-IBIG', 'PHIL-HEALTH', 'SALN'],
        self::TYPE_PROMOTION => ['SALN'],
    ];

    public function __construct(
        private readonly OnboardingRepository $onboardings,
        private readonly PlantillaService $plantilla,
        private readonly EmployeeWriteService $employeeWriter,
        private readonly EmployeeFormatterService $employeeFormatter,
    ) {
    }

    public function hasOnboarding(int $applicationId): bool
    {
        return Onboarding::withTrashed()
            ->where('plantilla_posting_application_id', $applicationId)
            ->exists();
    }

    public function createForCompletedApplication(PlantillaPostingApplication $application): Onboarding
    {
        PlantillaPostingApplication::whereKey($application->id)->lockForUpdate()->first();

        $existing = Onboarding::withTrashed()
            ->where('plantilla_posting_application_id', $application->id)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        $employee = $application->employee;
        $type     = $this->resolveType($employee);

        $onboarding = $this->onboardings->create([
            'employee_id'                      => $employee->id,
            'plantilla_posting_application_id' => $application->id,
            'name'                             => $employee->full_name,
            'position'                         => $this->limitOrNull($application->posting?->title),
            'department'                       => $this->limitOrNull($application->posting?->department?->name),
            'status'                           => 'Pending',
            'type'                             => $type,
        ]);

        $this->onboardings->createTasks($onboarding->id, self::REQUIREMENTS[$type]);

        return $onboarding->load('tasks');
    }

    public function provisionEmployee(Request $request, Onboarding $onboarding): ?Employee
    {
        $locked = Onboarding::whereKey($onboarding->id)->lockForUpdate()->firstOrFail();

        if ($locked->isProvisioned()) {
            return null;
        }

        $application = PlantillaPostingApplication::with('posting')->find($locked->plantilla_posting_application_id);
        $employee    = $locked->employee_id ? Employee::lockForUpdate()->find($locked->employee_id) : null;

        if (! $application || ! $employee) {
            throw ValidationException::withMessages([
                'status' => 'The linked PSB application or employee record no longer exists, so this onboarding cannot be completed.',
            ]);
        }

        $baseItemNumber = $application->posting?->base_item_number;

        if (! $baseItemNumber) {
            throw ValidationException::withMessages([
                'status' => 'The posting has no item number, so no plantilla slot can be assigned.',
            ]);
        }

        $current = $employee->assignments()
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->with('plantillaPosition')
            ->first();

        if ($current && $current->plantillaPosition?->base_item_number === $baseItemNumber) {
            $locked->update(['provisioned_at' => now()]);

            return null;
        }

        $position = PlantillaPosition::assignable()
            ->forItem($baseItemNumber)
            ->orderBy('slot_number')
            ->lockForUpdate()
            ->first();

        if (! $position) {
            throw ValidationException::withMessages([
                'status' => "No vacant plantilla slot is available for item {$baseItemNumber}, so the employee record could not be updated. Nothing was saved. Make a slot available and try again.",
            ]);
        }

        $today = now()->toDateString();

        try {
            if ($current) {
                $this->plantilla->vacate($current, $today, "Promoted via PSB application #{$application->id}");
            }

            $this->plantilla->assign($position->fresh(), $employee, null, $today);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        $attributes = [
            'employment_type' => 'Plantilla',
            'salary_override' => false,
        ];

        if ($position->display_division_id) {
            $attributes['division_id'] = $position->display_division_id;
        }

        if ($position->display_department_id) {
            $attributes['department_ids'] = [(int) $position->display_department_id];
        }

        $employee->update($attributes);

        $employee = $employee->fresh()->load([
            'primaryAssignment.plantillaPosition.salaryGrade',
            'primaryAssignment.stepIncrement',
        ]);

        $this->employeeWriter->syncSalaryHistory($employee);

        $locked->update(['provisioned_at' => now()]);

        ActivityLog::record(
            action:       'employee.plantilla_provisioned',
            userId:       $request->user()?->id,
            subjectType:  Employee::class,
            subjectId:    $employee->id,
            subjectLabel: $employee->full_name,
            payload:      [
                'onboarding_id'                    => $locked->id,
                'onboarding_type'                  => $locked->type,
                'plantilla_posting_application_id' => $application->id,
                'posting_title'                    => $application->posting?->title,
                'base_item_number'                 => $baseItemNumber,
                'plantilla_position_id'            => $position->id,
                'previous_assignment_id'           => $current?->id,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $employee;
    }

    public function broadcastEmployeeUpdated(Employee $employee): void
    {
        try {
            $employee->load([
                'info', 'parents', 'division',
                'primaryAssignment.plantillaPosition.salaryGrade',
                'primaryAssignment.stepIncrement',
                'cosPosition',
                'consultantPosition',
                'latestAssessment',
                'activeDevelopmentPlans.skill',
                'salaryHistories',
            ]);

            SafeBroadcast::send(new EmployeeUpdated($this->employeeFormatter->format($employee)), toOthers: true);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function resolveType(Employee $employee): string
    {
        $hasActivePlantilla = $employee->assignments()
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->exists();

        return $hasActivePlantilla ? self::TYPE_PROMOTION : self::TYPE_NEW;
    }

    private function limitOrNull(?string $value): ?string
    {
        return $value === null || $value === '' ? null : Str::limit($value, 100, '');
    }
}
