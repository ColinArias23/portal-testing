<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDepartmentAssignment;
use App\Services\Organization\EmployeeDepartmentAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeDepartmentAssignmentController extends Controller
{
    public function __construct(
        private readonly EmployeeDepartmentAssignmentService $assignments,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:employees.view')->only(['index']);
        $this->middleware('permission:employees.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Employee $employee): JsonResponse
    {
        return response()->json($this->assignments->listForEmployee($employee));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'department_id'      => ['required', 'exists:departments,id'],
            'is_primary'         => ['sometimes', 'boolean'],
            'department_head_id' => ['nullable', 'exists:employees,id'],
            'start_date'         => ['nullable', 'date'],
            'end_date'           => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $result = $this->assignments->create($employee, $data, $request);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Employee $employee, EmployeeDepartmentAssignment $assignment): JsonResponse
    {
        if (! $this->assignments->assertBelongsToEmployee($assignment, $employee)) {
            return response()->json(['message' => 'Assignment does not belong to this employee.'], 403);
        }

        $data = $request->validate([
            'is_primary'         => ['sometimes', 'boolean'],
            'department_head_id' => ['nullable', 'exists:employees,id'],
            'end_date'           => ['nullable', 'date'],
        ]);

        $formatted = $this->assignments->update($employee, $assignment, $data, $request);

        return response()->json($formatted);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Employee $employee, EmployeeDepartmentAssignment $assignment): JsonResponse
    {
        if (! $this->assignments->assertBelongsToEmployee($assignment, $employee)) {
            return response()->json(['message' => 'Assignment does not belong to this employee.'], 403);
        }

        if ($assignment->end_date) {
            return response()->json(['message' => 'This assignment has already been ended.'], 422);
        }

        $this->assignments->end($employee, $assignment, $request);

        return response()->json(['message' => 'Department assignment ended.']);
    }
}
