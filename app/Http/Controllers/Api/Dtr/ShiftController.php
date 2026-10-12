<?php

namespace App\Http\Controllers\Api\Dtr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dtr\AssignShiftRequest;
use App\Http\Requests\Dtr\SaveShiftRequest;
use App\Models\DtrEmployeeShift;
use App\Models\DtrShift;
use App\Models\Employee;
use App\Services\Dtr\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShiftController extends Controller
{
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

        return response()->json(['data' => $this->present($shift->loadCount('employeeShifts'))], 201);
    }

    public function update(SaveShiftRequest $request, DtrShift $shift): JsonResponse
    {
        $shift = $this->shifts->update($shift, $request->validated());

        return response()->json(['data' => $this->present($shift->loadCount('employeeShifts'))]);
    }

    public function destroy(DtrShift $shift): Response
    {
        $this->shifts->delete($shift);

        return response()->noContent();
    }

    public function assignments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:10,200'],
        ]);

        $page = Employee::query()
            ->when(! empty($data['q']), fn ($q) => $q->search($data['q']))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate((int) ($data['per_page'] ?? 50));

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
        $count = $this->shifts->assign(
            $request->validated('employee_ids'),
            $request->validated('shift_id') !== null ? (int) $request->validated('shift_id') : null,
        );

        return response()->json(['updated' => $count]);
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
