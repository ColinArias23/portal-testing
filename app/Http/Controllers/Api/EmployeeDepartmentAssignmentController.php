<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Department;
use App\Models\EmployeeDepartmentAssignment;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeDepartmentAssignmentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/employees/{employee}/department-assignments
     * 
     * List all department assignments for an employee (current and historical).
     */
    public function index(Employee $employee): JsonResponse
    {
        $assignments = $employee->departmentAssignments()
            ->with(['department.division', 'departmentHead'])
            ->orderByDesc('is_primary')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($a) => $this->format($a));

        return response()->json($assignments);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    /**
     * POST /api/employees/{employee}/department-assignments
     * 
     * Assign an employee to a department.
     * 
     * Body:
     *   {
     *     "department_id": 5,
     *     "is_primary": true,
     *     "department_head_id": null,
     *     "start_date": "2025-05-01"
     *   }
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'department_id'       => ['required', 'exists:departments,id'],
            'is_primary'          => ['sometimes', 'boolean'],
            'department_head_id'  => ['nullable', 'exists:employees,id'],
            'start_date'          => ['nullable', 'date'],
            'end_date'            => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // Check if employee is already assigned to this department
        $existing = EmployeeDepartmentAssignment::where('employee_id', $employee->id)
            ->where('department_id', $data['department_id'])
            ->whereNull('end_date')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Employee is already assigned to this department.',
                'assignment' => $this->format($existing),
            ], 422);
        }

        // If marking as primary, unmark existing primary
        if ($data['is_primary'] ?? false) {
            EmployeeDepartmentAssignment::where('employee_id', $employee->id)
                ->where('is_primary', true)
                ->whereNull('end_date')
                ->update(['is_primary' => false]);
        }

        // Create the assignment
        $assignment = EmployeeDepartmentAssignment::create([
            'employee_id'        => $employee->id,
            'department_id'      => $data['department_id'],
            'is_primary'         => $data['is_primary'] ?? true,
            'department_head_id' => $data['department_head_id'] ?? null,
            'start_date'         => $data['start_date'] ?? now()->toDateString(),
            'end_date'           => $data['end_date'] ?? null,
        ]);

        // Log the action
        ActivityLog::record(
            action:       'employee_department_assignment.created',
            userId:       $request->user()?->id,
            subjectType:  EmployeeDepartmentAssignment::class,
            subjectId:    $assignment->id,
            subjectLabel: "{$employee->full_name} → {$assignment->department->name}",
            payload:      [
                'employee_id'   => $employee->id,
                'department_id' => $data['department_id'],
                'is_primary'    => $assignment->is_primary,
            ],
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );

        return response()->json(
            $this->format($assignment->load(['department.division', 'departmentHead'])),
            201
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    /**
     * PATCH /api/employees/{employee}/department-assignments/{assignment}
     * 
     * Update a department assignment.
     */
    public function update(
        Request $request,
        Employee $employee,
        EmployeeDepartmentAssignment $assignment
    ): JsonResponse {
        // Verify assignment belongs to this employee
        if ($assignment->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'Assignment does not belong to this employee.',
            ], 403);
        }

        $data = $request->validate([
            'is_primary'          => ['sometimes', 'boolean'],
            'department_head_id'  => ['nullable', 'exists:employees,id'],
            'end_date'            => ['nullable', 'date'],
        ]);

        $before = [
            'is_primary'         => $assignment->is_primary,
            'department_head_id' => $assignment->department_head_id,
            'end_date'           => $assignment->end_date,
        ];

        // If marking as primary, unmark other primaries
        if (($data['is_primary'] ?? false) && !$assignment->is_primary) {
            EmployeeDepartmentAssignment::where('employee_id', $employee->id)
                ->where('is_primary', true)
                ->whereNull('end_date')
                ->update(['is_primary' => false]);
        }

        $assignment->update($data);

        ActivityLog::record(
            action:       'employee_department_assignment.updated',
            userId:       $request->user()?->id,
            subjectType:  EmployeeDepartmentAssignment::class,
            subjectId:    $assignment->id,
            subjectLabel: "{$employee->full_name} → {$assignment->department->name}",
            payload:      [
                'before' => $before,
                'after'  => [
                    'is_primary'         => $assignment->is_primary,
                    'department_head_id' => $assignment->department_head_id,
                    'end_date'           => $assignment->end_date,
                ],
            ],
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );

        return response()->json(
            $this->format($assignment->fresh(['department.division', 'departmentHead']))
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    /**
     * DELETE /api/employees/{employee}/department-assignments/{assignment}
     * 
     * End a department assignment by setting end_date to today.
     */
    public function destroy(
        Request $request,
        Employee $employee,
        EmployeeDepartmentAssignment $assignment
    ): JsonResponse {
        // Verify assignment belongs to this employee
        if ($assignment->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'Assignment does not belong to this employee.',
            ], 403);
        }

        // Prevent ending if already ended
        if ($assignment->end_date) {
            return response()->json([
                'message' => 'This assignment has already been ended.',
            ], 422);
        }

        $assignment->update(['end_date' => now()->toDateString()]);

        ActivityLog::record(
            action:       'employee_department_assignment.ended',
            userId:       $request->user()?->id,
            subjectType:  EmployeeDepartmentAssignment::class,
            subjectId:    $assignment->id,
            subjectLabel: "{$employee->full_name} → {$assignment->department->name}",
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );

        return response()->json(['message' => 'Department assignment ended.']);
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Format an assignment for JSON response.
     */
    private function format(EmployeeDepartmentAssignment $assignment): array
    {
        return [
            'id'                   => $assignment->id,
            'employee_id'          => $assignment->employee_id,
            'department_id'        => $assignment->department_id,
            'is_primary'           => $assignment->is_primary,
            'is_active'            => $assignment->is_active,
            'start_date'           => $assignment->start_date,
            'end_date'             => $assignment->end_date,

            'department'           => [
                'id'       => $assignment->department->id,
                'code'     => $assignment->department->code,
                'name'     => $assignment->department->name,
                'division' => $assignment->department->division ? [
                    'id'   => $assignment->department->division->id,
                    'name' => $assignment->department->division->name,
                ] : null,
            ],

            'department_head'      => $assignment->departmentHead ? [
                'id'        => $assignment->departmentHead->id,
                'full_name' => $assignment->departmentHead->full_name,
                'title'     => $assignment->departmentHead->title,
            ] : null,

            'effective_head'       => $assignment->effective_head ? [
                'id'        => $assignment->effective_head->id,
                'full_name' => $assignment->effective_head->full_name,
                'title'     => $assignment->effective_head->title,
            ] : null,

            'created_at'           => $assignment->created_at,
            'updated_at'           => $assignment->updated_at,
        ];
    }
}