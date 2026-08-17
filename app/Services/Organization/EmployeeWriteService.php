<?php

namespace App\Services\Organization;

use App\Events\Organization\EmployeeUpdated;
use App\Support\Broadcasting\SafeBroadcast;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeSalaryHistory;
use App\Models\PlantillaPosition;
use App\Models\User;
use App\Repositories\Shared\EmployeeRepository;
use App\Services\Recruitment\PlantillaPosting\PlantillaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeWriteService
{
    private const SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION = '23000';

    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly EmployeeFormatterService $formatter,
        private readonly EmployeeQueryService $queryService,
        private readonly PlantillaService $plantillaService,
    ) {
    }

    public function rules(): array
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

    public function assertUniqueEmployeeNumber(?string $number, ?int $ignoreId = null): void
    {
        if (! $number) {
            return;
        }

        $normalized = strtoupper(trim($number));

        if ($this->employees->employeeNumberExists($normalized, $ignoreId)) {
            throw ValidationException::withMessages([
                'employee_number' => ['The employee number has already been taken.'],
            ]);
        }
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === self::SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION;
    }

    public function store(Request $request): array
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

                $employee = $this->employees->create(
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
                $this->employees->syncParents($employee->id, $data['parent_ids'] ?? []);

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
                throw ValidationException::withMessages([
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

        return $this->formatter->format($employee);
    }

    public function update(Request $request, Employee $employee): array
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

        $pendingProvisionApplication = $this->queryService->findPendingProvisionApplication($employee);

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
                    $this->employees->syncParents($employee->id, $parentIds);
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
                throw ValidationException::withMessages([
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

        $formatted = $this->formatter->format($employee);
        SafeBroadcast::send(new EmployeeUpdated($formatted), toOthers: true);

        return $formatted;
    }

    /**
     * @return array{status:int, body:array}
     */
    public function destroy(Request $request, Employee $employee): array
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
                        $this->plantillaService->vacate(
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

                $this->employees->deleteHierarchyFor($employee->id);

                EmployeeAssignment::where('employee_id', $employee->id)->delete();

                if ($employee->info) {
                    $employee->info()->delete();
                }

                $linkedUser = User::where('employee_id', $employee->id)
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

            return [
                'status' => 200,
                'body' => [
                    'message' => "Employee '{$label}' deleted successfully.",
                    'success' => true,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Employee deletion failed', [
                'employee_id'   => $id,
                'employee_name' => $label,
                'user_id'       => Auth::id(),
                'error_message' => $e->getMessage(),
                'error_code'    => $e->getCode(),
                'trace'         => $e->getTraceAsString(),
            ]);

            return [
                'status' => 500,
                'body' => [
                    'message' => 'Failed to delete employee. Please check with administrator.',
                    'error'   => 'deletion_failed',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ];
        }
    }

    public function avatarUrl(Employee $employee, Request $request): ?string
    {
        Log::channel('confidential_access')->info('Avatar accessed', [
            'subject_emp_id' => $employee->id,
            'accessed_by'    => Auth::id() ?? 'unauthenticated',
            'ip'             => $request->ip(),
            'at'             => now()->toIso8601String(),
        ]);

        return $this->formatter->resolveAvatarUrlPublic($employee->getRawOriginal('avatar_url'));
    }

    private function storeAvatar(\Illuminate\Http\UploadedFile $file): string
    {
        $path = 'avatars/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

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

    private function syncSalaryHistory(Employee $employee): void
    {
        $snapshot = $this->formatter->resolveSalarySnapshot($employee);

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

        if (! $request->has('plantilla_position_id')) {
            return;
        }

        $newPositionId = $request->plantilla_position_id ? (int) $request->plantilla_position_id : null;
        $newStepId     = $request->step_increment_id     ? (int) $request->step_increment_id     : null;

        $existing = EmployeeAssignment::where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->first();

        $existingPositionId = $existing ? (int) $existing->plantilla_position_id : null;
        $existingStepId     = $existing ? (int) $existing->step_increment_id     : null;

        if ($existingPositionId === $newPositionId && $existingStepId === $newStepId) {
            return;
        }

        if ($existing) {
            $this->plantillaService->vacate(
                $existing,
                now()->toDateString(),
                'Reassigned via employee form'
            );
        }

        if (! $newPositionId) {
            return;
        }

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