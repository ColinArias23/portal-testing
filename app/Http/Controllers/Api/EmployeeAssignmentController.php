<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\PlantillaPosition;
use App\Services\PlantillaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeAssignmentController extends Controller
{
    public function __construct(private PlantillaService $service)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:employees.view')->only(['index']);
        $this->middleware('permission:employees.manage')->only(['store', 'end', 'destroy']);
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(Employee $employee): JsonResponse
    {
        $assignments = EmployeeAssignment::with([
            'plantillaPosition.item',
            'plantillaPosition.salaryGrade',
            'stepIncrement',
        ])
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (EmployeeAssignment $a) => $this->format($a));

        return response()->json($assignments);
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

        $employee = Employee::findOrFail($data['employee_id']);
        $position = PlantillaPosition::findOrFail($data['plantilla_position_id']);

        try {
            $assignment = $this->service->assign(
                position:        $position,
                employee:        $employee,
                stepIncrementId: isset($data['step_increment_id'])
                    ? (int) $data['step_increment_id']
                    : null,
                startDate: $data['start_date'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(
            $this->format($assignment->load([
                'plantillaPosition.item',
                'plantillaPosition.salaryGrade',
                'stepIncrement',
            ])),
            201
        );
    }
// ─── End ─────────────────────────────────────────────────────
    public function end(Request $request, EmployeeAssignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'end_date' => ['nullable', 'date'],
            'remarks'  => ['nullable', 'string'],
        ]);

        try {
            $this->service->vacate(
                assignment: $assignment,
                endDate:    $data['end_date']  ?? null,
                remarks:    $data['remarks']   ?? null,
            );
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

        $assignment->delete();

        return response()->json(['message' => 'Assignment deleted.']);
    }
// ─── Format ─────────────────────────────────────────────────────
    private function format(EmployeeAssignment $a): array
    {
        $position = $a->plantillaPosition;
        $step     = $a->effectiveStepIncrement();

        return [
            'id'         => $a->id,
            'is_primary' => $a->is_primary,
            'is_active'  => $a->isActive(),
            'start_date' => $a->start_date,
            'end_date'   => $a->end_date,

            'plantilla_position' => $position ? [
                'id'             => $position->id,
                'item_number'    => $position->item_number,
                'position_title' => $position->position_title,
                'status'         => $position->computed_status,
                'item'           => $position->item ? [
                    'id'    => $position->item->id,
                    'title' => $position->item->title,
                ] : null,
                'salary_grade' => $position->salaryGrade ? [
                    'id'             => $position->salaryGrade->id,
                    'salary_grade'   => $position->salaryGrade->salary_grade,
                    'monthly_salary' => $position->salaryGrade->monthly_salary,
                    'annual_salary'  => $position->salaryGrade->annual_salary,
                ] : null,
            ] : null,

            'step_increment' => $step ? [
                'id'             => $step->id,
                'step'           => $step->step,
                'monthly_salary' => $step->monthly_salary,
                'annual_salary'  => $step->annual_salary,
            ] : null,

            'created_at' => $a->created_at,
            'updated_at' => $a->updated_at,
        ];
    }
}