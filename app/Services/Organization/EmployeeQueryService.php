<?php

namespace App\Services\Organization;

use App\Models\Employee;
use App\Models\PlantillaPosition;
use App\Models\PlantillaPostingApplication;
use App\Repositories\Shared\EmployeeRepository;
use Illuminate\Http\Request;

class EmployeeQueryService
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly EmployeeFormatterService $formatter,
    ) {
    }

    public function index(Request $request): array
    {
        $perPage    = max(1, (int) ($request->per_page ?? self::PER_PAGE));
        $page       = max(1, (int) ($request->page ?? 1));
        $allDepts   = $this->employees->cachedDepartmentsById();
        $divisionId = $request->filled('division_id') ? $request->integer('division_id') : null;
        $deptId     = $request->filled('department_id') ? (int) $request->department_id : null;

        if ($request->filled('search')) {
            return $this->search($request->get('search'), $divisionId, $deptId, $page, $perPage, $allDepts);
        }

        $paginator = $this->employees
            ->baseQuery($divisionId, $deptId)
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(
            fn ($employee) => $this->formatter->format($employee, $allDepts)
        );

        return $paginator->toArray();
    }

    private function search(
        string $term,
        ?int $divisionId,
        ?int $deptId,
        int $page,
        int $perPage,
        $allDepts
    ): array {
        $term = strtolower(trim($term));

        $candidates = $this->employees->searchCandidatesQuery($divisionId, $deptId)->get();

        $matchingIds = $candidates
            ->filter(function (Employee $emp) use ($term) {
                $fullName = collect([$emp->prefix, $emp->first_name, $emp->middle_name, $emp->last_name, $emp->suffix])
                    ->filter()
                    ->implode(' ');

                return str_contains(strtolower($fullName), $term)
                    || collect($emp->role_position ?? [])
                        ->contains(fn ($r) => str_contains(strtolower($r), $term));
            })
            ->pluck('id')
            ->values();

        $total    = $matchingIds->count();
        $lastPage = (int) ceil($total / $perPage) ?: 1;
        $pageIds  = $matchingIds->slice(($page - 1) * $perPage, $perPage)->values()->all();

        $items = $this->employees->findManyWithFullRelations($pageIds)
            ->map(fn ($emp) => $this->formatter->format($emp, $allDepts));

        return [
            'data'         => $items->values()->all(),
            'current_page' => $page,
            'last_page'    => $lastPage,
            'per_page'     => $perPage,
            'total'        => $total,
            'from'         => $total ? ($page - 1) * $perPage + 1 : null,
            'to'           => $total ? min($page * $perPage, $total) : null,
        ];
    }

    public function show(Employee $employee): array
    {
        $employee = $this->employees->findWithFullRelations($employee);

        return $this->formatter->format($employee);
    }

    public function team(): array
    {
        $allDepts  = $this->employees->cachedDepartmentsById();
        $employees = $this->employees->teamQuery()->get();

        return $employees->map(fn ($e) => $this->formatter->format($e, $allDepts))->values()->all();
    }

    public function nonRoot(): array
    {
        $rootIds   = $this->employees->rootEmployeeIds();
        $employees = $this->employees->nonRootEmployees($rootIds);

        return $employees->map(fn ($emp) => [
            'id'        => $emp->id,
            'full_name' => $emp->full_name,
            'division'  => $emp->division?->only(['id', 'name']),
            'skill_summary' => $emp->latestAssessment ? [
                'average_score' => $emp->latestAssessment->average_score,
                'gap_count'     => $emp->latestAssessment->gap_count,
            ] : null,
        ])->values()->all();
    }

    public function genderCount(): array
    {
        $counts = $this->employees->genderCounts();

        return [
            'male'   => $counts['male'],
            'female' => $counts['female'],
            'total'  => $counts['male'] + $counts['female'],
        ];
    }

    public function findPendingProvisionApplication(Employee $employee): ?PlantillaPostingApplication
    {
        return PlantillaPostingApplication::where('employee_id', $employee->id)
            ->where('status', 'Completed')
            ->with('posting')
            ->latest('reviewed_at')
            ->get()
            ->first(function (PlantillaPostingApplication $application) use ($employee) {
                $baseItemNumber = $application->posting?->base_item_number;
                if (! $baseItemNumber) {
                    return false;
                }
                $hasMatchingActiveAssignment = $employee->assignments()
                    ->whereNull('end_date')
                    ->whereHas(
                        'plantillaPosition',
                        fn ($q) => $q->where('base_item_number', $baseItemNumber)
                    )
                    ->exists();

                return ! $hasMatchingActiveAssignment;
            });
    }

    public function pendingPlantillaProvision(Employee $employee): array
    {
        $application = $this->findPendingProvisionApplication($employee);

        if (! $application) {
            return ['pending' => false];
        }

        $vacantPosition = PlantillaPosition::where('base_item_number', $application->posting->base_item_number)
            ->vacant()
            ->with(['displayDepartment:id,name,code', 'displayDivision:id,name,code', 'salaryGrade', 'stepIncrement'])
            ->orderBy('slot_number')
            ->first();

        if (! $vacantPosition) {
            return ['pending' => false];
        }

        return [
            'pending'            => true,
            'application_id'     => $application->id,
            'posting_title'      => $application->posting->title,
            'plantilla_position' => [
                'id'                 => $vacantPosition->id,
                'base_item_number'   => $vacantPosition->base_item_number,
                'position_slot_name' => $vacantPosition->position_slot_name,
                'position_title'     => $vacantPosition->position_title,
                'salary_grade_id'    => $vacantPosition->salary_grade_id,
                'salary_grade'       => $vacantPosition->salaryGrade?->salary_grade,
                'step_increment_id'  => $vacantPosition->step_increment_id,
                'display_department' => $vacantPosition->displayDepartment?->only(['id', 'name', 'code']),
                'display_division'   => $vacantPosition->displayDivision?->only(['id', 'name', 'code']),
            ],
        ];
    }
}
