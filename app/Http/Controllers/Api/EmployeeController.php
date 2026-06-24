<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHierarchy;
use App\Models\EmployeeInfo;
use App\Models\EmployeeAssignment;
use App\Models\PlantillaPosition;
use App\Services\PlantillaService;
use App\Events\EmployeeUpdated;
use App\Traits\ResolvesAvatarUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    use ResolvesAvatarUrl;

    private const PER_PAGE = 999999;

    public function __construct(private PlantillaService $plantillaService)
    {
        $this->middleware('auth:sanctum');

        $this->middleware('role:SuperAdmin|HR')->only([
            'store',
            'update',
            'destroy',
        ]);
    }

    private function formatEmployee(Employee $employee): array
    {
        $allDepts = Department::all(['id', 'name'])->keyBy('id');

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

        $latestAssessment = $employee->relationLoaded('latestAssessment')
            ? $employee->latestAssessment
            : $employee->latestAssessment()->first();

        $activePlans = $employee->relationLoaded('activeDevelopmentPlans')
            ? $employee->activeDevelopmentPlans
            : $employee->activeDevelopmentPlans()->with('skill')->get();

        return [
            'id'                   => $employee->id,
            'employee_number'      => $employee->employee_number,
            'prefix'               => $employee->prefix,
            'first_name'           => $employee->first_name,
            'middle_name'          => $employee->middle_name,
            'last_name'            => $employee->last_name,
            'suffix'               => $employee->suffix,
            'full_name'            => $employee->full_name,
            'role_position'        => $employee->role_position        ?? [],
            'position_designation' => $employee->position_designation ?? [],
            'title'                => $employee->title                ?? [],
            'employment_type'      => $employee->employment_type,
            'employment_status'    => $employee->employment_status,
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

    private function assertUniqueEmployeeNumber(?string $number, $ignoreId = null): void
    {
        if (! $number) {
            return;
        }

        $number = strtoupper(trim($number));

        $exists = Employee::whereRaw('UPPER(TRIM(employee_number)) = ?', [$number])
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            abort(422, json_encode([
                'employee_number' => ['The employee number has already been taken.'],
            ]));
        }
    }

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

            $all->each(function ($emp) use ($allDepts) {
                $emp->departments = collect($emp->department_ids ?? [])
                    ->map(fn($id) => $allDepts->get($id))
                    ->filter()->values()->toArray();
                $emp->avatar_url = $this->resolveAvatarUrl($emp->getRawOriginal('avatar_url'));
            });

            $total    = $all->count();
            $lastPage = (int) ceil($total / $perPage) ?: 1;
            $items    = $all->slice(($page - 1) * $perPage, $perPage)->values();

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

        $paginator->getCollection()->transform(function ($employee) use ($allDepts) {
            $employee->departments = collect($employee->department_ids ?? [])
                ->map(fn($id) => $allDepts->get($id))
                ->filter()->values()->toArray();
            $employee->avatar_url = $this->resolveAvatarUrl($employee->getRawOriginal('avatar_url'));
            return $employee;
        });

        return response()->json($paginator);
    }

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
        ]);

        return response()->json($this->formatEmployee($employee));
    }

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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $this->assertUniqueEmployeeNumber($request->employee_number);

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

            return $employee->fresh()->load([
                'info', 'parents', 'division',
                'primaryAssignment.plantillaPosition.salaryGrade',
                'primaryAssignment.stepIncrement',
                'cosPosition',
                'consultantPosition',
                'latestAssessment',
                'activeDevelopmentPlans.skill',
            ]);
        });

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

            return $employee->fresh()->load([
                'info', 'parents', 'division',
                'primaryAssignment.plantillaPosition.salaryGrade',
                'primaryAssignment.stepIncrement',
                'cosPosition',
                'consultantPosition',
                'latestAssessment',
                'activeDevelopmentPlans.skill',
            ]);
        });

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

        $formatted = $this->formatEmployee($employee);
        broadcast(new EmployeeUpdated($formatted))->toOthers();

        return response()->json($formatted);
    }

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

    private function syncAssignment(Employee $employee, Request $request): void
    {
        if ($request->employment_type !== 'Plantilla') {
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