<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Services\Organization\EmployeeAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeAssignmentController extends Controller
{
    public function __construct(
        private readonly EmployeeAssignmentService $assignments,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:employees.view')->only(['index']);
        $this->middleware('permission:employees.manage')->only(['store', 'end', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Employee $employee): JsonResponse
    {
        return response()->json($this->assignments->listForEmployee($employee));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'           => ['required', 'exists:employees,id'],
            'plantilla_position_id' => ['required', 'exists:plantilla_positions,id'],
            'step_increment_id'     => ['nullable', 'exists:step_increments,id'],
            'start_date'            => ['nullable', 'date'],
        ]);

        try {
            $formatted = $this->assignments->create($data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($formatted, 201);
    }

    // ─── End ─────────────────────────────────────────────────────
    public function end(Request $request, EmployeeAssignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'end_date' => ['nullable', 'date'],
            'remarks'  => ['nullable', 'string'],
        ]);

        try {
            $this->assignments->end($assignment, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Assignment closed. Position is now UNFILLED and awaiting approval.']);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(EmployeeAssignment $assignment): JsonResponse
    {
        if ($assignment->isActive()) {
            return response()->json([
                'message' => 'Cannot delete an active assignment. Use the end endpoint to close it first.',
            ], 422);
        }

        $this->assignments->delete($assignment);

        return response()->json(['message' => 'Assignment deleted.']);
    }
}
