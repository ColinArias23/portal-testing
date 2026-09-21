<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Http\Resources\Leave\LeaveBalanceResource;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeaveBalanceController extends Controller
{
    public function __construct(private readonly LeaveBalanceService $balanceService)
    {
    }

    public function roster(Request $request)
    {
        $this->authorize('viewAny', LeaveBalance::class);

        $employees = Employee::query()
            ->plantilla()
            ->select([
                'id',
                'employee_number',
                'prefix',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'employment_type',
            ])
            ->with(['primaryDepartmentAssignment.department:id,name'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_id' => $employee->employee_number,
                'name' => $employee->full_name,
                'department' => $employee->primary_department?->name,
                'department_id' => $employee->primary_department?->id,
                'employment_type' => $employee->employment_type,
            ])
            ->values();

        return response()->json(['data' => $employees]);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', LeaveBalance::class);

        $year = (int) $request->query('year', now()->year);

        $balances = LeaveBalance::query()
            ->where('year', $year)
            ->with(['employee', 'leaveType'])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->input('employee_id')))
            ->when($request->filled('leave_type_id'), fn ($q) => $q->where('leave_type_id', $request->input('leave_type_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas(
                'employee.primaryDepartmentAssignment',
                fn ($eq) => $eq->where('department_id', $request->input('department_id'))
            ))
            ->paginate($request->integer('per_page', 20));

        return LeaveBalanceResource::collection($balances);
    }

    public function forEmployee(Request $request, Employee $employee)
    {
        $this->authorize('view', [LeaveBalance::class, $employee]);

        $year = (int) $request->query('year', now()->year);

        $balances = $employee->leaveBalances()
            ->with('leaveType')
            ->where('year', $year)
            ->get();

        return LeaveBalanceResource::collection($balances);
    }

    public function adjust(Request $request)
    {
        $this->authorize('adjust', LeaveBalance::class);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'amount' => ['required', 'numeric'],
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        $balance = $this->balanceService->adjust(
            $employee,
            $leaveType,
            $data['year'],
            (float) $data['amount'],
            $data['remarks'],
        );

        return new LeaveBalanceResource($balance->load('leaveType'));
    }

    public function setCredits(Request $request, Employee $employee, LeaveType $leaveType)
    {
        $this->authorize('adjust', LeaveBalance::class);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'total' => ['required', 'numeric', 'min:0'],
            'used' => ['required', 'numeric', 'min:0'],
            'carry_forward' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['used'] > $data['total'] + $data['carry_forward']) {
            throw ValidationException::withMessages([
                'used' => 'Used credits cannot exceed total + carried-forward credits.',
            ]);
        }

        $balance = $this->balanceService->setCredits(
            $employee,
            $leaveType,
            $data['year'],
            [
                'opening_balance' => $data['total'],
                'carry_forward_in' => $data['carry_forward'],
                'used' => $data['used'],
            ],
            $data['remarks'] ?? "Credits set by {$request->user()->name}",
            $request->user()->id,
        );

        return new LeaveBalanceResource($balance->load('leaveType'));
    }

    public function resetCredits(Request $request, Employee $employee, LeaveType $leaveType)
    {
        $this->authorize('adjust', LeaveBalance::class);

        $year = (int) $request->query('year', now()->year);

        $balance = $this->balanceService->resetCredits($employee, $leaveType, $year, $request->user()->id);

        return new LeaveBalanceResource($balance->load('leaveType'));
    }
}