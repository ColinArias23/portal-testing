<?php

namespace App\Http\Controllers\Api\Dtr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dtr\AssignShiftRequest;
use App\Http\Requests\Dtr\SaveShiftRequest;
use App\Models\ActivityLog;
use App\Models\DtrEmployeeShift;
use App\Models\DtrShift;
use App\Models\Employee;
use App\Services\Dtr\ShiftService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    private const ASSIGNMENT_SORTS = ['employee_number', 'name', 'assigned_shift'];

    public function __construct(private readonly ShiftService $shifts)
    {
    }

    public function index(): JsonResponse
    {
        $rows = DtrShift::query()
            ->withCount('employeeShifts')
            ->orderByDesc('is_default')
            ->orderBy('start_time')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows->map(fn (DtrShift $s) => $this->present($s))->all()]);
    }

    public function store(SaveShiftRequest $request): JsonResponse
    {
        $shift = $this->shifts->create($request->validated());

        $this->audit($request, 'dtr.shift.created', $shift, ['after' => $this->snapshot($shift)]);

        return response()->json(['data' => $this->present($shift->loadCount('employeeShifts'))], 201);
    }

    public function update(SaveShiftRequest $request, DtrShift $shift): JsonResponse
    {
        $before = $this->snapshot($shift);

        $shift = $this->shifts->update($shift, $request->validated());

        $this->audit($request, 'dtr.shift.updated', $shift, [
            'before' => $before,
            'after'  => $this->snapshot($shift),
        ]);

        return response()->json(['data' => $this->present($shift->loadCount('employeeShifts'))]);
    }

    public function destroy(Request $request, DtrShift $shift): Response
    {
        $before = $this->snapshot($shift);

        $this->shifts->delete($shift);

        $this->audit($request, 'dtr.shift.deleted', $shift, ['before' => $before]);

        return response()->noContent();
    }

    public function assignments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'         => ['nullable', 'string', 'max:100'],
            'shift'     => ['nullable', 'regex:/^(none|\d+)$/'],
            'sort'      => ['nullable', Rule::in(self::ASSIGNMENT_SORTS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page'  => ['nullable', 'integer', 'between:10,200'],
        ]);

        $page = $this->assignmentsQuery($data)->paginate((int) ($data['per_page'] ?? 50));

        $assigned = DtrEmployeeShift::query()
            ->whereIn('employee_id', $page->pluck('id'))
            ->with('shift')
            ->get()
            ->keyBy('employee_id');

        $default = $this->shifts->default();

        return response()->json($page->through(function (Employee $employee) use ($assigned, $default) {
            $own       = $assigned->get($employee->id)?->shift;
            $effective = $own?->is_active ? $own : $default;

            return [
                'id'               => $employee->id,
                'employee_number'  => $employee->employee_number,
                'name'             => $employee->full_name,
                'assigned_shift'   => $this->shifts->label($own),
                'effective_shift'  => $this->shifts->label($effective),
                'uses_default'     => ! $own?->is_active,
            ];
        }));
    }

    public function assign(AssignShiftRequest $request): JsonResponse
    {
        $employeeIds = $request->validated('employee_ids');
        $shiftId     = $request->validated('shift_id') !== null ? (int) $request->validated('shift_id') : null;

        $count = $this->shifts->assign($employeeIds, $shiftId);

        ActivityLog::record(
            action: $shiftId ? 'dtr.shift.assigned' : 'dtr.shift.unassigned',
            userId: $request->user()?->id,
            subjectType: DtrShift::class,
            subjectId: $shiftId,
            subjectLabel: $shiftId ? DtrShift::query()->whereKey($shiftId)->value('name') : null,
            payload: ['employee_ids' => $employeeIds, 'count' => $count],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['updated' => $count]);
    }

    private function assignmentsQuery(array $data): Builder
    {
        $query = Employee::query()
            ->select('employees.*')
            ->when(! empty($data['q']), fn ($q) => $q->search($data['q']));

        $shift = $data['shift'] ?? null;

        if ($shift === 'none') {
            $query->whereNotIn('employees.id', DtrEmployeeShift::query()->select('employee_id'));
        } elseif ($shift !== null) {
            $query->whereIn(
                'employees.id',
                DtrEmployeeShift::query()->where('shift_id', (int) $shift)->select('employee_id'),
            );
        }

        $direction = ($data['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        match ($data['sort'] ?? 'name') {
            'employee_number' => $query
                ->orderByRaw('employees.employee_number is null')
                ->orderBy('employees.employee_number', $direction),
            'assigned_shift'  => $query
                ->leftJoin('dtr_employee_shifts', 'dtr_employee_shifts.employee_id', '=', 'employees.id')
                ->leftJoin('dtr_shifts', 'dtr_shifts.id', '=', 'dtr_employee_shifts.shift_id')
                ->orderByRaw('dtr_shifts.name is null')
                ->orderBy('dtr_shifts.name', $direction),
            default           => $query
                ->orderBy('employees.last_name', $direction)
                ->orderBy('employees.first_name', $direction),
        };

        return $query->orderBy('employees.id');
    }

    private function snapshot(DtrShift $shift): array
    {
        return [
            'name'          => $shift->name,
            'start_time'    => substr($shift->start_time, 0, 5),
            'end_time'      => substr($shift->end_time, 0, 5),
            'grace_minutes' => $shift->grace_minutes,
            'is_default'    => $shift->is_default,
            'is_active'     => $shift->is_active,
        ];
    }

    private function audit(Request $request, string $action, DtrShift $shift, array $payload): void
    {
        ActivityLog::record(
            action: $action,
            userId: $request->user()?->id,
            subjectType: DtrShift::class,
            subjectId: $shift->id,
            subjectLabel: $shift->name,
            payload: $payload,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    private function present(DtrShift $shift): array
    {
        return [
            'id'              => $shift->id,
            'name'            => $shift->name,
            'start_time'      => substr($shift->start_time, 0, 5),
            'end_time'        => substr($shift->end_time, 0, 5),
            'grace_minutes'   => $shift->grace_minutes,
            'is_default'      => $shift->is_default,
            'is_active'       => $shift->is_active,
            'crosses_midnight' => $shift->crosses_midnight,
            'employees_count' => $shift->employee_shifts_count ?? 0,
        ];
    }
}