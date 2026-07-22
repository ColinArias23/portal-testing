<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHierarchy;
use App\Models\EmployeeInfo;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeSalaryHistory;
use App\Models\PlantillaPosition;
use App\Models\PlantillaPostingApplication;
use App\Services\Recruitment\PlantillaPosting\PlantillaService;
use App\Events\Organization\EmployeeUpdated;
use App\Traits\Concerns\ResolvesAvatarUrl;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    use ResolvesAvatarUrl;

    private const PER_PAGE = 20;
    private const SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION = '23000';

    public function __construct(private PlantillaService $plantillaService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:employees.view')->only(['index', 'show', 'nonRoot', 'genderCount', 'avatar', 'pendingPlantillaProvision']);
        $this->middleware('permission:employees.manage')->only(['store', 'update', 'destroy']);
        $this->middleware('permission:team.view')->only(['team']);
    }
// ─── FormatEmployee ─────────────────────────────────────────────────────
    private function formatEmployee(Employee $employee, ?Collection $allDepts = null): array
    {
        $allDepts ??= Department::all(['id', 'name'])->keyBy('id');

        $departments = collect($employee->department_ids ?? [])
            ->map(fn($id) => $allDepts->get($id))
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
            'parents' => $employee->parents->map(fn($p) => [
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

            'development_plans' => $activePlans->map(fn($plan) => [
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
// ─── EmployeeQuery ─────────────────────────────────────────────────────
    private function employeeQuery(Request $request)
    {
        $query = Employee::with([
            'info',
            'parents',
            'division',
            'user',
            'primaryAssignment.plantillaPosition.salaryGrade',
            'primaryAssignment.stepIncrement',
            'cosPosition',
            'consultantPosition',
            'latestAssessment',
            'activeDevelopmentPlans.skill',
            'salaryHistories',
        ]);

        if ($request->filled('division_id')) {
            $query->where('division_id', $request->integer('division_id'));
        }

        if ($request->filled('department_id')) {
            $deptId = (int) $request->department_id;
            $query->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode($deptId)]);
        }

        return $query->orderBy('id');
    }
// ─── ResolveAnnualSalary ─────────────────────────────────────────────────────
    private function resolveAnnualSalary(Employee $employee, ?string $employmentType = null, $stepIncrement = null): ?string
    {
        $employmentType ??= strtolower(trim($employee->employment_type ?? ''));
        $stepIncrement  ??= $employee->primaryAssignment?->effectiveStepIncrement();

        if ($employmentType === 'plantilla' && ! $employee->salary_override && $stepIncrement) {
            return $stepIncrement->annual_salary;
        }

        return $employee->annual_salary;
    }
// ─── ResolveMonthlySalary ─────────────────────────────────────────────────────
    private function resolveMonthlySalary(Employee $employee, ?string $employmentType = null, $stepIncrement = null): ?string
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
// ─── ResolveSalarySnapshot ───────────────────────────────────────────────────
    private function resolveSalarySnapshot(Employee $employee): array
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
// ─── FindPendingProvisionApplication ─────────────────────────────────────────
    private function findPendingProvisionApplication(Employee $employee): ?PlantillaPostingApplication
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
// ─── ReconcileManualSalaryInputs ─────────────────────────────────────────────
    private function reconcileManualSalaryInputs(Request $request, array $data): array
    {
        $hasMonthly = $request->filled('monthly_salary');
        $hasAnnual  = $request->filled('annual_salary');

        if (! $hasMonthly && ! $hasAnnual) {
            return $data;
        }

        $source  = $request->input('salary_input_source');
        $monthly = $hasMonthly ? round((float) $request->monthly_salary, 2) : null;
        $annual  = $hasAnnual  ? round((float) $request->annual_salary, 2)  : null;

        if ($source === 'monthly' && $monthly !== null) {
            $annual = round($monthly * 12, 2);
        } elseif ($source === 'annual' && $annual !== null) {
            $monthly = round($annual / 12, 2);
        } elseif ($monthly !== null && $annual === null) {
            $annual = round($monthly * 12, 2);
        } elseif ($annual !== null && $monthly === null) {
            $monthly = round($annual / 12, 2);
        }

        $data['monthly_salary'] = $monthly;
        $data['annual_salary']  = $annual;

        return $data;
    }
// ─── SyncSalaryHistory ───────────────────────────────────────────────────────
    private function syncSalaryHistory(Employee $employee): void
    {
        $snapshot = $this->resolveSalarySnapshot($employee);

        if ($snapshot['annual_salary'] === null && $snapshot['gross_salary'] === null) {
            return;
        }

        $current = EmployeeSalaryHistory::where('employee_id', $employee->id)
            ->whereNull('end_date')
            ->latest('effective_date')
            ->latest('id')
            ->first();

        $changed = ! $current
            || round((float) $current->annual_salary, 2) !== round((float) ($snapshot['annual_salary'] ?? 0), 2)
            || round((float) $current->gross_salary, 2)  !== round((float) ($snapshot['gross_salary'] ?? 0), 2)
            || (string) ($current->salary_grade ?? '') !== (string) ($snapshot['salary_grade'] ?? '')
            || (string) ($current->step ?? '')         !== (string) ($snapshot['step'] ?? '');

        if (! $changed) {
            return;
        }

        $today = now()->toDateString();

        if ($current && $current->effective_date?->toDateString() === $today) {
            $current->update([
                'gross_salary'  => $snapshot['gross_salary'],
                'annual_salary' => $snapshot['annual_salary'],
                'salary_grade'  => $snapshot['salary_grade'],
                'step'          => $snapshot['step'],
            ]);
            return;
        }

        if ($current) {
            $current->update(['end_date' => $today]);
        }

        EmployeeSalaryHistory::create([
            'employee_id'    => $employee->id,
            'gross_salary'   => $snapshot['gross_salary'],
            'annual_salary'  => $snapshot['annual_salary'],
            'salary_grade'   => $snapshot['salary_grade'],
            'step'           => $snapshot['step'],
            'effective_date' => $today,
            'end_date'       => null,
        ]);
    }
// ─── Rules ─────────────────────────────────────────────────────
    private function rules(): array
    {
        return [
            'employee_number'        => ['sometimes'],
            'role_position'          => ['nullable', 'array'],
            'role_position.*'        => ['string'],
            'department_ids'         => ['nullable', 'array'],
            'department_ids.*'       => ['exists:departments,id'],
            'division_id'            => ['nullable', 'exists:divisions,id'],
            'prefix'                 => ['nullable'],
            'first_name'             => ['sometimes'],
            'middle_name'            => ['nullable'],
            'last_name'              => ['sometimes'],
            'suffix'                 => ['nullable'],
            'title'                  => ['nullable', 'array'],
            'title.*'                => ['string'],
            'employment_type'        => ['nullable', 'in:Plantilla,Contract of Service,Consultant'],
            'employment_status'      => ['nullable', 'string'],
            'annual_salary'          => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'monthly_salary'         => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'salary_override'        => ['nullable', 'boolean'],
            'salary_input_source'    => ['nullable', 'in:monthly,annual'],
            'position_designation'   => ['nullable', 'array'],
            'position_designation.*' => ['string'],
            'plantilla_position_id'  => ['nullable', 'exists:plantilla_positions,id'],
            'step_increment_id'      => ['nullable', 'exists:step_increments,id'],
            'cos_position_id'        => ['nullable', 'exists:cos_positions,id'],
            'consultant_position_id' => ['nullable', 'exists:consultant_positions,id'],
            'gender'                 => ['nullable', 'in:MALE,FEMALE'],
            'address'                => ['nullable'],
            'birthdate'              => ['nullable', 'date'],
            'contact'                => ['nullable'],
            'avatar_url'             => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'parent_ids'             => ['nullable', 'array'],
            'parent_ids.*'           => ['exists:employees,id'],
        ];
    }
// ─── AssertUniqueEmployeeNumber ─────────────────────────────────────────────────────
    private function assertUniqueEmployeeNumber(?string $number, $ignoreId = null): void
    {
        if (! $number) {
            return;
        }

        $number = strtoupper(trim($number));

        $exists = Employee::whereRaw('UPPER(TRIM(employee_number)) = ?', [$number])
            ->when($ignoreId !== null, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'employee_number' => ['The employee number has already been taken.'],
            ]);
        }
    }
// ─── IsUniqueConstraintViolation ─────────────────────────────────────────────
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === self::SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION;
    }
// ─── GenderCount ─────────────────────────────────────────────────────
    public function genderCount(): JsonResponse
    {
        $male   = EmployeeInfo::where('gender', 'MALE')->count();
        $female = EmployeeInfo::where('gender', 'FEMALE')->count();

        return response()->json([
            'male'   => $male,
            'female' => $female,
            'total'  => $male + $female,
        ]);
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $perPage  = max(1, (int) ($request->per_page ?? self::PER_PAGE));
        $page     = max(1, (int) ($request->page    ?? 1));
        $allDepts = Department::all(['id', 'name'])->keyBy('id');
        $query    = $this->employeeQuery($request);

        if ($request->filled('search')) {
            $term = strtolower(trim($request->search));

            $all = $query->get()->filter(function ($emp) use ($term) {
                return str_contains(strtolower($emp->full_name), $term)
                    || collect($emp->role_position ?? [])
                        ->contains(fn($r) => str_contains(strtolower($r), $term));
            })->values();

            $total    = $all->count();
            $lastPage = (int) ceil($total / $perPage) ?: 1;
            $items    = $all->slice(($page - 1) * $perPage, $perPage)
                ->values()
                ->map(fn($emp) => $this->formatEmployee($emp, $allDepts));

            return response()->json([
                'data'         => $items,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'from'         => $total ? ($page - 1) * $perPage + 1 : null,
                'to'           => $total ? min($page * $perPage, $total) : null,
            ]);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(
            fn($employee) => $this->formatEmployee($employee, $allDepts)
        );

        return response()->json($paginator);
    }
// ─── PendingPlantillaProvision ────────────────────────────────────────────────
    public function pendingPlantillaProvision(Employee $employee): JsonResponse
    {
        $application = $this->findPendingProvisionApplication($employee);

        if (! $application) {
            return response()->json(['pending' => false]);
        }

        $vacantPosition = PlantillaPosition::where('base_item_number', $application->posting->base_item_number)
            ->vacant()
            ->with(['displayDepartment:id,name,code', 'displayDivision:id,name,code', 'salaryGrade', 'stepIncrement'])
            ->orderBy('slot_number')
            ->first();

        if (! $vacantPosition) {
            return response()->json(['pending' => false]);
        }

        return response()->json([
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
        ]);
}
// ─── Show ─────────────────────────────────────────────────────
    public function show(Employee $employee): JsonResponse
    {
        $employee->load([
            'info',
            'parents',
            'division',
            'user',
            'primaryAssignment.plantillaPosition.salaryGrade',
            'primaryAssignment.stepIncrement',
            'cosPosition',
            'consultantPosition',
            'latestAssessment',
            'activeDevelopmentPlans.skill',
            'salaryHistories',
        ]);

        return response()->json($this->formatEmployee($employee));
    }
// ─── Team ─────────────────────────────────────────────────────
    public function team(): JsonResponse
    {
        $allDepts = Department::all(['id', 'name'])->keyBy('id');

        $employees = Employee::with([
            'info', 'parents', 'division', 'user',
            'primaryAssignment.plantillaPosition.salaryGrade',
            'primaryAssignment.stepIncrement',
            'cosPosition', 'consultantPosition',
            'latestAssessment', 'activeDevelopmentPlans.skill', 'salaryHistories',
        ])->orderBy('id')->get();

        return response()->json(
            $employees->map(fn($e) => $this->formatEmployee($e, $allDepts))->values()
        );
    }
// ─── NonRoot ─────────────────────────────────────────────────────
    public function nonRoot(): JsonResponse
    {
        $rootIds = DB::table('employee_hierarchy')
            ->whereNull('parent_id')
            ->pluck('employee_id');

        $employees = Employee::with(['division', 'latestAssessment'])
            ->whereNotIn('id', $rootIds)
            ->orderBy('last_name')
            ->get()
            ->map(fn($emp) => [
                'id'        => $emp->id,
                'full_name' => $emp->full_name,
                'division'  => $emp->division?->only(['id', 'name']),
                'skill_summary' => $emp->latestAssessment ? [
                    'average_score' => $emp->latestAssessment->average_score,
                    'gap_count'     => $emp->latestAssessment->gap_count,
                ] : null,
            ]);

        return response()->json($employees);
    }
// ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $this->assertUniqueEmployeeNumber($request->employee_number);

        try {
            $employee = DB::transaction(function () use ($request, $data) {
                if ($request->hasFile('avatar_url')) {
                    $data['avatar_url'] = $this->storeAvatar($request->file('avatar_url'));
                }

                $data['department_ids']       = array_map('intval', $request->department_ids ?? []);
                $data['role_position']        = $request->role_position        ?? [];
                $data['title']                = $request->title                ?? [];
                $data['position_designation'] = $request->position_designation ?? [];

                $data['cos_position_id']        = $request->cos_position_id        ?? null;
                $data['consultant_position_id'] = $request->consultant_position_id ?? null;

                $data = $this->reconcileManualSalaryInputs($request, $data);
                $data['salary_override'] = $request->boolean('salary_override');

                $employee = Employee::create(
                    collect($data)->except([
                        'parent_ids', 'plantilla_position_id', 'step_increment_id',
                        'gender', 'address', 'birthdate', 'contact',
                    ])->toArray()
                );

                $employee->info()->create([
                    'gender'    => $request->gender,
                    'address'   => $request->address,
                    'birthdate' => $request->birthdate,
                    'contact'   => $request->contact,
                ]);

                $this->syncAssignment($employee, $request);
                $this->syncParents($employee, $data['parent_ids'] ?? []);

                $employee = $employee->fresh()->load([
                    'info', 'parents', 'division',
                    'primaryAssignment.plantillaPosition.salaryGrade',
                    'primaryAssignment.stepIncrement',
                    'cosPosition',
                    'consultantPosition',
                    'latestAssessment',
                    'activeDevelopmentPlans.skill',
                ]);

                $this->syncSalaryHistory($employee);

                return $employee->load('salaryHistories');
            });
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'employee_number' => ['The employee number has already been taken.'],
                ]);
            }

            throw $e;
        }

        ActivityLog::record(
            action:       'employee.created',
            userId:       Auth::id(),
            subjectType:  Employee::class,
            subjectId:    $employee->id,
            subjectLabel: $employee->full_name,
            payload:      [
                'employee_number' => $employee->employee_number,
                'employment_type' => $employee->employment_type,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($this->formatEmployee($employee), 201);
    }
// ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate($this->rules());
        $this->assertUniqueEmployeeNumber($request->employee_number, $employee->id);

        $before = [
            'full_name'       => $employee->full_name,
            'employment_type' => $employee->employment_type,
            'division_id'     => $employee->division_id,
            'department_ids'  => $employee->department_ids,
            'role_position'   => $employee->role_position,
        ];

        $pendingProvisionApplication = $this->findPendingProvisionApplication($employee);

        try {
            $employee = DB::transaction(function () use ($request, $employee, $data) {
                if ($request->hasFile('avatar_url')) {
                    $oldPath = $employee->getRawOriginal('avatar_url');
                    if ($oldPath && file_exists(storage_path('app/public/' . $oldPath))) {
                        Storage::disk('public')->delete($oldPath);
                    }
                    $data['avatar_url'] = $this->storeAvatar($request->file('avatar_url'));
                }

                $data['department_ids']       = array_map('intval', $request->department_ids ?? []);
                $data['role_position']        = $request->role_position        ?? [];
                $data['title']                = $request->title                ?? [];
                $data['position_designation'] = $request->position_designation ?? [];

                $data['cos_position_id']        = $request->cos_position_id        ?? null;
                $data['consultant_position_id'] = $request->consultant_position_id ?? null;

                $data = $this->reconcileManualSalaryInputs($request, $data);
                $data['salary_override'] = $request->boolean('salary_override');

                $employee->update(
                    collect($data)->except([
                        'parent_ids', 'plantilla_position_id', 'step_increment_id',
                        'gender', 'address', 'birthdate', 'contact',
                    ])->toArray()
                );

                $employee->info()->updateOrCreate(
                    ['employee_id' => $employee->id],
                    [
                        'gender'    => $request->gender,
                        'address'   => $request->address,
                        'birthdate' => $request->birthdate,
                        'contact'   => $request->contact,
                    ]
                );

                $this->syncAssignment($employee, $request);

                if ($request->input('_sync_parents') === '1') {
                    $parentIds = array_filter(
                        array_map('intval', (array) ($request->input('parent_ids') ?? []))
                    );
                    $this->syncParents($employee, $parentIds);
                }

                $employee = $employee->fresh()->load([
                    'info', 'parents', 'division',
                    'primaryAssignment.plantillaPosition.salaryGrade',
                    'primaryAssignment.stepIncrement',
                    'cosPosition',
                    'consultantPosition',
                    'latestAssessment',
                    'activeDevelopmentPlans.skill',
                ]);

                $this->syncSalaryHistory($employee);

                return $employee->load('salaryHistories');
            });
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'employee_number' => ['The employee number has already been taken.'],
                ]);
            }

            throw $e;
        }

        ActivityLog::record(
            action:       'employee.updated',
            userId:       Auth::id(),
            subjectType:  Employee::class,
            subjectId:    $employee->id,
            subjectLabel: $employee->full_name,
            payload:      [
                'before' => $before,
                'after'  => [
                    'full_name'       => $employee->full_name,
                    'employment_type' => $employee->employment_type,
                    'division_id'     => $employee->division_id,
                    'department_ids'  => $employee->department_ids,
                    'role_position'   => $employee->role_position,
                ],
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        if ($pendingProvisionApplication && strtolower(trim($employee->employment_type ?? '')) === 'plantilla') {
            $nowProvisioned = $employee->assignments()
                ->whereNull('end_date')
                ->whereHas(
                    'plantillaPosition',
                    fn ($q) => $q->where('base_item_number', $pendingProvisionApplication->posting->base_item_number)
                )
                ->exists();

            if ($nowProvisioned) {
                ActivityLog::record(
                    action:       'employee.plantilla_provisioned',
                    userId:       Auth::id(),
                    subjectType:  Employee::class,
                    subjectId:    $employee->id,
                    subjectLabel: $employee->full_name,
                    payload:      [
                        'plantilla_posting_application_id' => $pendingProvisionApplication->id,
                        'posting_title'                     => $pendingProvisionApplication->posting->title,
                        'base_item_number'                  => $pendingProvisionApplication->posting->base_item_number,
                        'plantilla_position_id'              => $data['plantilla_position_id'] ?? null,
                    ],
                    ip:        $request->ip(),
                    userAgent: $request->userAgent(),
                );
            }
        }

        $formatted = $this->formatEmployee($employee);
        broadcast(new EmployeeUpdated($formatted))->toOthers();

        return response()->json($formatted);
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $label = $employee->full_name;
        $id    = $employee->id;

        try {
            DB::transaction(function () use ($employee) {
                $active = EmployeeAssignment::where('employee_id', $employee->id)
                    ->whereNull('end_date')
                    ->where('is_primary', true)
                    ->first();

                if ($active) {
                    try {
                        app(PlantillaService::class)->vacate(
                            $active,
                            now()->toDateString(),
                            'Employee record deleted'
                        );
                    } catch (\Throwable $e) {
                        Log::warning('PlantillaService::vacate failed during deletion, using fallback', [
                            'employee_id'   => $employee->id,
                            'assignment_id' => $active->id,
                            'error'         => $e->getMessage(),
                        ]);
                        $active->update(['end_date' => now()->toDateString()]);
                    }
                }

                $path = $employee->getRawOriginal('avatar_url');
                if ($path && file_exists(storage_path('app/public/' . $path))) {
                    try {
                        Storage::disk('public')->delete($path);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to delete avatar file during employee deletion', [
                            'path'  => $path,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                EmployeeHierarchy::where('employee_id', $employee->id)
                    ->orWhere('parent_id', $employee->id)
                    ->delete();

                EmployeeAssignment::where('employee_id', $employee->id)
                    ->delete();

                if ($employee->info) {
                    $employee->info()->delete();
                }

                $linkedUser = \App\Models\User::where('employee_id', $employee->id)
                    ->whereNotNull('employee_id')
                    ->first();

                if ($linkedUser && ! $linkedUser->hasAnyRole(['SuperAdmin', 'HR'])) {
                    $linkedUser->tokens()->delete(); 
                    $linkedUser->delete();           
                }

                $employee->delete();
            }, attempts: 3);

            ActivityLog::record(
                action:       'employee.deleted',
                userId:       Auth::id(),
                subjectType:  Employee::class,
                subjectId:    $id,
                subjectLabel: $label,
                ip:           $request->ip(),
                userAgent:    $request->userAgent(),
            );

            return response()->json([
                'message' => "Employee '{$label}' deleted successfully.",
                'success' => true,
            ]);

        } catch (\Throwable $e) {
            Log::error('Employee deletion failed', [
                'employee_id'   => $id,
                'employee_name' => $label,
                'user_id'       => Auth::id(),
                'error_message' => $e->getMessage(),
                'error_code'    => $e->getCode(),
                'trace'         => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to delete employee. Please check with administrator.',
                'error'   => 'deletion_failed',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
// ─── Avatar ─────────────────────────────────────────────────────
    public function avatar(Request $request, Employee $employee): JsonResponse
    {
        Log::channel('confidential_access')->info('Avatar accessed', [
            'subject_emp_id' => $employee->id,
            'accessed_by'    => Auth::id() ?? 'unauthenticated',
            'ip'             => $request->ip(),
            'at'             => now()->toIso8601String(),
        ]);

        return response()->json([
            'url' => $this->resolveAvatarUrl($employee->getRawOriginal('avatar_url')),
        ]);
    }

    private function storeAvatar(\Illuminate\Http\UploadedFile $file): string
    {
        $path = 'avatars/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));
        return $path;
    }

    private function syncParents(Employee $employee, array $parents): void
    {
        EmployeeHierarchy::where('employee_id', $employee->id)->delete();

        foreach (array_filter($parents) as $parentId) {
            EmployeeHierarchy::create([
                'employee_id' => $employee->id,
                'parent_id'   => $parentId,
            ]);
        }
    }
// ─── SyncAssignment ─────────────────────────────────────────────────────
    private function syncAssignment(Employee $employee, Request $request): void
    {
        if ($request->employment_type !== 'Plantilla') {
            $existing = EmployeeAssignment::where('employee_id', $employee->id)
                ->where('is_primary', true)
                ->whereNull('end_date')
                ->first();

            if ($existing) {
                $this->plantillaService->vacate(
                    $existing,
                    now()->toDateString(),
                    'Employee moved to ' . $request->employment_type . ' via employee form'
                );
            }

            return;
        }

        if (! $request->has('plantilla_position_id')) return;

        $newPositionId = $request->plantilla_position_id ? (int) $request->plantilla_position_id : null;
        $newStepId     = $request->step_increment_id     ? (int) $request->step_increment_id     : null;

        $existing = EmployeeAssignment::where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->first();

        $existingPositionId = $existing ? (int) $existing->plantilla_position_id : null;
        $existingStepId     = $existing ? (int) $existing->step_increment_id     : null;

        if (
            $existingPositionId === $newPositionId &&
            $existingStepId     === $newStepId
        ) {
            return;
        }

        if ($existing) {
            $this->plantillaService->vacate(
                $existing,
                now()->toDateString(),
                'Reassigned via employee form'
            );
        }

        if (! $newPositionId) return;

        $position = PlantillaPosition::findOrFail($newPositionId);

        if ($existingPositionId === $newPositionId) {
            $this->plantillaService->approve($position->fresh());
        }

        $this->plantillaService->assign(
            position:        $position->fresh(),
            employee:        $employee,
            stepIncrementId: $newStepId ?: null,
            startDate:       now()->toDateString(),
        );
    }
}