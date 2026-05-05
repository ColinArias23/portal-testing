<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlantillaPosition;
use App\Models\StepIncrement;
use App\Models\EmployeeAssignment;
use App\Models\ActivityLog;
use App\Services\PlantillaService;
use Illuminate\Http\Request;

class PlantillaPositionController extends Controller
{
    public function __construct(private PlantillaService $service) {}

    // ─── Index ────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $q = PlantillaPosition::with([
            'item.division',
            'salaryGrade',
            'stepIncrement',
            'assignments' => fn ($q) => $q
                ->whereNull('end_date')
                ->where('is_primary', true)
                ->with('employee:id,first_name,last_name,prefix,suffix'),
        ]);

        if ($request->filled('plantilla_item_id')) {
            $q->where('plantilla_item_id', $request->plantilla_item_id);
        }

        if ($request->filled('salary_grade_id')) {
            $q->where('salary_grade_id', $request->salary_grade_id);
        }

        if ($request->filled('division_id')) {
            $q->forDivision((int) $request->division_id);
        }

        if ($request->filled('department_id')) {
            $q->forDepartment((int) $request->department_id);
        }

        if ($request->filled('status')) {
            match (strtoupper($request->status)) {
                'VACANT'   => $q->vacant(),
                'FILLED'   => $q->filled(),
                'UNFILLED' => $q->unfilled(),
                default    => null,
            };
        }

        $currentPositionId = $request->filled('employee_id')
            ? EmployeeAssignment::where('employee_id', $request->employee_id)
                ->whereNull('end_date')
                ->value('plantilla_position_id')
            : null;

        return $q->orderByRaw('CAST(item_number AS UNSIGNED)')
            ->get()
            ->map(fn (PlantillaPosition $pos) => $this->format($pos, $currentPositionId));
    }

    // ─── Show ─────────────────────────────────────────────────────────────

    public function show(PlantillaPosition $plantillaPosition)
    {
        $plantillaPosition->load(['item.division', 'salaryGrade', 'stepIncrement']);

        return response()->json($this->format($plantillaPosition));
    }

    // ─── Steps ────────────────────────────────────────────────────────────

    public function steps(PlantillaPosition $plantillaPosition)
    {
        $sgId = $plantillaPosition->salary_grade_id;

        if (! $sgId) {
            return response()->json([]);
        }

        return StepIncrement::where('salary_grade_id', $sgId)
            ->orderBy('step')
            ->get(['id', 'step', 'monthly_salary', 'annual_salary']);
    }

    // ─── Update ───────────────────────────────────────────────────────────

    public function update(Request $request, PlantillaPosition $plantillaPosition)
    {
        $data = $request->validate([
            'position_title'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'salary_grade_id'    => ['sometimes', 'nullable', 'exists:salary_grades,id'],
            'step_increment_id'  => ['sometimes', 'nullable', 'exists:step_increments,id'],
            'date_of_assumption' => ['sometimes', 'nullable', 'date'],
            'vacancy_notes'      => ['sometimes', 'nullable', 'string'],
            'requires_approval'  => ['sometimes', 'boolean'],
        ]);

        // plantilla_item_id is not editable — strip even if sent
        unset($data['plantilla_item_id']);

        if (isset($data['requires_approval'])) {
            if ($data['requires_approval'] === false) {
                $data['status']     = 'VACANT';
                $data['vacated_at'] = null;
            } else {
                $data['status']     = 'UNFILLED';
                $data['vacated_at'] = now();
            }
        }

        $plantillaPosition->update($data);

        return response()->json(
            $this->format(
                $plantillaPosition->fresh(['item.division', 'salaryGrade', 'stepIncrement'])
            )
        );
    }

    // ─── Destroy ──────────────────────────────────────────────────────────

    public function destroy(PlantillaPosition $plantillaPosition)
    {
        try {
            $this->service->deletePosition($plantillaPosition);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Slot removed.']);
    }

    // ─── Approve ──────────────────────────────────────────────────────────

    public function approve(Request $request, PlantillaPosition $plantillaPosition)
    {
        if (! $plantillaPosition->requires_approval) {
            return response()->json([
                'message' => 'This slot does not require approval.',
            ], 422);
        }

        try {
            $this->service->approve($plantillaPosition);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        ActivityLog::record(
            action:       'plantilla_position.approved',
            userId:       $request->user()?->id,
            subjectType:  PlantillaPosition::class,
            subjectId:    $plantillaPosition->id,
            subjectLabel: "Slot #{$plantillaPosition->item_number} — {$plantillaPosition->item?->title}",
            payload:      ['approved_by' => $request->user()?->id],
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );

        return response()->json([
            'message'  => 'Slot approved and reopened for assignment.',
            'position' => $this->format(
                $plantillaPosition->fresh(['item.division', 'salaryGrade', 'stepIncrement'])
            ),
        ]);
    }

    // ─── Private ──────────────────────────────────────────────────────────

    private function format(PlantillaPosition $pos, ?int $currentPositionId = null): array
    {
        $data = $pos->toArray();

        $data['computed_status']     = $pos->computed_status;
        $data['is_assignable']       = $pos->isAssignable();
        $data['is_current_employee'] = $currentPositionId !== null && $pos->id === $currentPositionId;

        // Include division and department context from parent item
        $data['division']        = $pos->item?->division?->only(['id', 'name']);
        $data['department_ids']  = $pos->item?->department_ids ?? [];

        return $data;
    }
}