<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlantillaPosition;
use App\Models\StepIncrement;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlantillaPositionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // ─── Index ────────────────────────────────────────────────────────────

    /**
     * GET /api/plantilla-positions
     *
     * List all plantilla positions with filtering.
     * Excludes soft-deleted records by default.
     */
    public function index(Request $request)
    {
        $query = PlantillaPosition::query()
            ->with([
                'division',
                'salaryGrade',
                'stepIncrement',
                'activeAssignments.employee:id,first_name,last_name,prefix,suffix',
            ])
            ->orderBy('base_item_number')
            ->orderBy('slot_number');

        // Filter by base item number
        if ($request->filled('base_item_number')) {
            $query->where('base_item_number', $request->base_item_number);
        }

        // Filter by division
        if ($request->filled('division_id')) {
            $query->forDivision((int) $request->division_id);
        }

        // Filter by department
        if ($request->filled('department_id')) {
            $query->forDepartment((int) $request->department_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            match (strtoupper($request->status)) {
                'VACANT'   => $query->vacant(),
                'FILLED'   => $query->filled(),
                'UNFILLED' => $query->unfilled(),
                default    => null,
            };
        }

        // Filter by salary grade
        if ($request->filled('salary_grade_id')) {
            $query->where('salary_grade_id', $request->salary_grade_id);
        }

        // Include or exclude deleted records
        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }

        $positions = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $positions->getCollection()
                ->map(fn(PlantillaPosition $pos) => $this->format($pos)),
            'pagination' => [
                'total'        => $positions->total(),
                'per_page'     => $positions->perPage(),
                'current_page' => $positions->currentPage(),
                'last_page'    => $positions->lastPage(),
            ],
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────────────

    /**
     * GET /api/plantilla-positions/{id}
     */
    public function show(PlantillaPosition $plantillaPosition)
    {
        $plantillaPosition->load([
            'division',
            'salaryGrade.stepIncrements',
            'stepIncrement',
            'activeAssignments.employee',
        ]);

        return response()->json($this->format($plantillaPosition));
    }

    // ─── Steps ────────────────────────────────────────────────────────────

    /**
     * GET /api/plantilla-positions/{id}/steps
     *
     * Return all step increments available for this position's salary grade.
     */
    public function steps(PlantillaPosition $plantillaPosition)
    {
        if (!$plantillaPosition->salary_grade_id) {
            return response()->json([]);
        }

        $steps = StepIncrement::where('salary_grade_id', $plantillaPosition->salary_grade_id)
            ->orderBy('step')
            ->get(['id', 'step', 'monthly_salary', 'annual_salary']);

        return response()->json($steps);
    }

    // ─── Store ────────────────────────────────────────────────────────────

    /**
     * POST /api/plantilla-positions
     *
     * Create a new plantilla item with auto-generated positions.
     *
     * Single position:   item_no = "22"
     * Multiple positions: item_no = "23-1", "23-2", "23-3", etc.
     *
     * Payload:
     * {
     *   "base_item_number": "22",
     *   "title": "Pharmacist III",
     *   "description": "Pharmacist Position Level III",
     *   "slots": 1,
     *   "salary_grade_id": 18,
     *   "division_id": 1,
     *   "department_ids": [1, 2]
     * }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'base_item_number'  => ['required', 'string', 'max:20'],
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'slots'             => ['required', 'integer', 'min:1', 'max:99'],
            'division_id'       => ['nullable', 'integer', 'exists:divisions,id'],
            'department_ids'    => ['nullable', 'array'],
            'department_ids.*'  => ['integer', 'exists:departments,id'],
            'salary_grade_id'   => ['nullable', 'integer', 'exists:salary_grades,id'],
            'step_increment_id' => ['nullable', 'integer', 'exists:step_increments,id'],
        ]);

        $slots = $data['slots'];
        unset($data['slots']);

        DB::transaction(function () use ($data, $slots, $request) {
            for ($i = 1; $i <= $slots; $i++) {
                $itemNo = $slots === 1
                    ? $data['base_item_number']
                    : "{$data['base_item_number']}-{$i}";

                PlantillaPosition::create([
                    ...$data,
                    'slot_number'       => $i,
                    'position_slot_name' => $itemNo,
                    'status'            => 'VACANT',
                    'requires_approval' => false,
                ]);
            }

            ActivityLog::record(
                action: 'plantilla_position.batch_created',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                subjectLabel: "{$data['title']} ({$slots} position" . ($slots > 1 ? 's' : '') . ")",
                payload: [
                    'base_item_number' => $data['base_item_number'],
                    'title'            => $data['title'],
                    'slots'            => $slots,
                    'division_id'      => $data['division_id'] ?? null,
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        $positions = PlantillaPosition::where('base_item_number', $data['base_item_number'])
            ->with(['division', 'salaryGrade', 'stepIncrement'])
            ->get();

        return response()->json([
            'message'   => "Created {$slots} position" . ($slots > 1 ? 's' : '') . " for {$data['title']}.",
            'positions' => $positions->map(fn(PlantillaPosition $pos) => $this->format($pos)),
        ], 201);
    }

    // ─── Add Slots (Expansion) ────────────────────────────────────────────

    /**
     * POST /api/plantilla-positions/add-slots
     *
     * Add more slots to an existing plantilla item.
     *
     * Payload:
     * {
     *   "base_item_number": "001",
     *   "count": 3
     * }
     */
    public function addSlots(Request $request)
    {
        $data = $request->validate([
            'base_item_number' => ['required', 'string', 'exists:plantilla_positions'],
            'count'            => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $highest = PlantillaPosition::where('base_item_number', $data['base_item_number'])
            ->max('slot_number') ?? 0;

        $template = PlantillaPosition::where('base_item_number', $data['base_item_number'])
            ->first();

        if (!$template) {
            return response()->json(['message' => 'Plantilla item not found.'], 404);
        }

        DB::transaction(function () use ($template, $data, $highest, $request) {
            for ($i = 1; $i <= $data['count']; $i++) {
                $newSlotNumber = $highest + $i;

                $slotName = PlantillaPosition::where('base_item_number', $template->base_item_number)
                    ->count() === 1
                    ? $template->title
                    : "{$template->title}-{$newSlotNumber}";

                PlantillaPosition::create([
                    'base_item_number'   => $template->base_item_number,
                    'title'              => $template->title,
                    'description'        => $template->description,
                    'slot_number'        => $newSlotNumber,
                    'position_slot_name' => $slotName,
                    'division_id'        => $template->division_id,
                    'department_ids'     => $template->department_ids,
                    'salary_grade_id'    => $template->salary_grade_id,
                    'step_increment_id'  => $template->step_increment_id,
                    'status'             => 'VACANT',
                    'requires_approval'  => false,
                ]);
            }

            ActivityLog::record(
                action: 'plantilla_position.slots_added',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                subjectLabel: "{$template->title}",
                payload: [
                    'base_item_number' => $template->base_item_number,
                    'slots_added'      => $data['count'],
                    'new_total'        => $highest + $data['count'],
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return response()->json([
            'message' => "Added {$data['count']} new slot(s) to {$template->title}.",
        ]);
    }

    // ─── Update ───────────────────────────────────────────────────────────

    /**
     * PATCH /api/plantilla-positions/{id}
     */
    public function update(Request $request, PlantillaPosition $plantillaPosition)
    {
        $data = $request->validate([
            'position_title'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'salary_grade_id'   => ['sometimes', 'nullable', 'integer', 'exists:salary_grades,id'],
            'step_increment_id' => ['sometimes', 'nullable', 'integer', 'exists:step_increments,id'],
            'date_of_assumption' => ['sometimes', 'nullable', 'date'],
            'vacancy_notes'     => ['sometimes', 'nullable', 'string'],
            'requires_approval' => ['sometimes', 'boolean'],
        ]);

        $before = [
            'salary_grade_id'   => $plantillaPosition->salary_grade_id,
            'step_increment_id' => $plantillaPosition->step_increment_id,
            'position_title'    => $plantillaPosition->position_title,
        ];

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

        ActivityLog::record(
            action: 'plantilla_position.updated',
            userId: $request->user()?->id,
            subjectType: PlantillaPosition::class,
            subjectId: $plantillaPosition->id,
            subjectLabel: $plantillaPosition->position_slot_name,
            payload: [
                'before' => $before,
                'after'  => [
                    'salary_grade_id'   => $plantillaPosition->salary_grade_id,
                    'step_increment_id' => $plantillaPosition->step_increment_id,
                    'position_title'    => $plantillaPosition->position_title,
                ],
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(
            $this->format($plantillaPosition->fresh(['division', 'salaryGrade', 'stepIncrement']))
        );
    }

    // ─── Soft Delete ──────────────────────────────────────────────────────

    /**
     * DELETE /api/plantilla-positions/{id}
     *
     * Soft delete a position. Cannot delete FILLED or UNFILLED positions.
     */
    public function destroy(Request $request, PlantillaPosition $plantillaPosition)
    {
        if ($plantillaPosition->isFilled()) {
            return response()->json([
                'message' => "Cannot delete [{$plantillaPosition->position_slot_name}] — it is currently FILLED.",
            ], 422);
        }

        if ($plantillaPosition->isUnfilled()) {
            return response()->json([
                'message' => "Cannot delete [{$plantillaPosition->position_slot_name}] — it is UNFILLED and awaiting approval.",
            ], 422);
        }

        $plantillaPosition->delete();

        ActivityLog::record(
            action: 'plantilla_position.deleted',
            userId: $request->user()?->id,
            subjectType: PlantillaPosition::class,
            subjectId: $plantillaPosition->id,
            subjectLabel: $plantillaPosition->position_slot_name,
            payload: [
                'base_item_number' => $plantillaPosition->base_item_number,
                'slot_number'      => $plantillaPosition->slot_number,
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['message' => 'Position slot deleted.']);
    }

    // ─── Bulk Soft Delete ─────────────────────────────────────────────────

    /**
     * POST /api/plantilla-positions/bulk-delete
     *
     * Payload:
     * {
     *   "ids": [1, 2, 3, 4, 5]
     * }
     */
    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:plantilla_positions,id'],
        ]);

        $positions = PlantillaPosition::whereIn('id', $data['ids'])->get();

        $unfillable = $positions->filter(fn($p) => $p->isFilled() || $p->isUnfilled());

        if ($unfillable->isNotEmpty()) {
            $names = $unfillable->pluck('position_slot_name')->implode(', ');
            return response()->json([
                'message'          => "Cannot delete these positions (they are FILLED or UNFILLED): {$names}",
                'unfillable_count' => $unfillable->count(),
            ], 422);
        }

        DB::transaction(function () use ($positions, $request) {
            $positions->each(fn($pos) => $pos->delete());

            ActivityLog::record(
                action: 'plantilla_position.bulk_deleted',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                payload: [
                    'count'     => $positions->count(),
                    'positions' => $positions->pluck('position_slot_name'),
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return response()->json([
            'message' => "Deleted {$positions->count()} position slot(s).",
            'count'   => $positions->count(),
        ]);
    }

    // ─── Restore ──────────────────────────────────────────────────────────

    /**
     * PATCH /api/plantilla-positions/{id}/restore
     */
    public function restore(Request $request, $id)
    {
        $position = PlantillaPosition::onlyTrashed()->find($id);

        if (!$position) {
            return response()->json(['message' => 'Position not found (or not deleted).'], 404);
        }

        $position->restore();

        ActivityLog::record(
            action: 'plantilla_position.restored',
            userId: $request->user()?->id,
            subjectType: PlantillaPosition::class,
            subjectId: $position->id,
            subjectLabel: $position->position_slot_name,
            payload: [
                'base_item_number' => $position->base_item_number,
                'slot_number'      => $position->slot_number,
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'message'  => 'Position restored.',
            'position' => $this->format($position->fresh(['division', 'salaryGrade', 'stepIncrement'])),
        ]);
    }

    // ─── Bulk Restore ─────────────────────────────────────────────────────

    /**
     * POST /api/plantilla-positions/bulk-restore
     *
     * Payload:
     * {
     *   "ids": [1, 2, 3]
     * }
     */
    public function bulkRestore(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $positions = PlantillaPosition::onlyTrashed()
            ->whereIn('id', $data['ids'])
            ->get();

        if ($positions->isEmpty()) {
            return response()->json(['message' => 'No deleted positions found with those IDs.'], 404);
        }

        DB::transaction(function () use ($positions, $request) {
            $positions->each(fn($pos) => $pos->restore());

            ActivityLog::record(
                action: 'plantilla_position.bulk_restored',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                payload: [
                    'count'     => $positions->count(),
                    'positions' => $positions->pluck('position_slot_name'),
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return response()->json([
            'message' => "Restored {$positions->count()} position slot(s).",
            'count'   => $positions->count(),
        ]);
    }

    // ─── Approve ──────────────────────────────────────────────────────────

    /**
     * POST /api/plantilla-positions/{id}/approve
     */
    public function approve(Request $request, PlantillaPosition $plantillaPosition)
    {
        if (!$plantillaPosition->requires_approval) {
            return response()->json([
                'message' => 'This position does not require approval.',
            ], 422);
        }

        $plantillaPosition->approveForRefilling();

        ActivityLog::record(
            action: 'plantilla_position.approved',
            userId: $request->user()?->id,
            subjectType: PlantillaPosition::class,
            subjectId: $plantillaPosition->id,
            subjectLabel: $plantillaPosition->position_slot_name,
            payload: ['approved_by' => $request->user()?->id],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'message'  => 'Position approved and reopened for assignment.',
            'position' => $this->format($plantillaPosition->fresh(['division', 'salaryGrade', 'stepIncrement'])),
        ]);
    }

    // ─── Summary ──────────────────────────────────────────────────────────

    /**
     * GET /api/plantilla-positions/summary/all
     */
    public function summary()
    {
        return response()->json([
            'total_positions' => PlantillaPosition::count(),
            'filled'          => PlantillaPosition::filled()->count(),
            'vacant'          => PlantillaPosition::vacant()->count(),
            'unfilled'        => PlantillaPosition::unfilled()->count(),
            'overstaffed'     => PlantillaPosition::overstaffed()->count(),
            'deleted'         => PlantillaPosition::onlyTrashed()->count(),
            'by_division'     => PlantillaPosition::groupBy('division_id')
                ->selectRaw('division_id, count(*) as count')
                ->with('division:id,name')
                ->get()
                ->map(fn($item) => [
                    'division_id' => $item->division_id,
                    'division'    => $item->division?->name,
                    'count'       => $item->count,
                ]),
        ]);
    }

    // ─── Salary Config ────────────────────────────────────────────────────

    /**
     * GET /api/plantilla-positions/{id}/salary-config
     */
    public function salaryConfig(PlantillaPosition $plantillaPosition)
    {
        $plantillaPosition->load(['salaryGrade.stepIncrements', 'stepIncrement']);

        return response()->json($plantillaPosition->formatSalaryInfo());
    }

    // ─── Available Items ──────────────────────────────────────────────────

    /**
     * GET /api/plantilla-positions/available/items
     */
    public function availableItems()
    {
        $items = PlantillaPosition::selectRaw('DISTINCT base_item_number, title, division_id')
            ->with('division')
            ->vacant()
            ->get()
            ->unique('base_item_number')
            ->map(fn($pos) => [
                'base_item_number' => $pos->base_item_number,
                'title'            => $pos->title,
                'division'         => $pos->division?->only(['id', 'name']),
                'available_count'  => PlantillaPosition::where('base_item_number', $pos->base_item_number)
                    ->vacant()
                    ->count(),
            ])
            ->values();

        return response()->json($items);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────

    private function format(PlantillaPosition $position): array
    {
        $activeAssignments = $position->relationLoaded('activeAssignments')
            ? $position->activeAssignments
            : $position->activeAssignments()->get();

        $sg = $position->relationLoaded('salaryGrade')
            ? $position->salaryGrade
            : $position->salaryGrade()->first();

        $step = $position->relationLoaded('stepIncrement')
            ? $position->stepIncrement
            : $position->stepIncrement()->first();

        return [
            'id'                 => $position->id,
            'base_item_number'   => $position->base_item_number,
            'title'              => $position->title,
            'description'        => $position->description,
            'slot_number'        => $position->slot_number,
            'position_slot_name' => $position->position_slot_name,
            'position_title'     => $position->position_title,
            'status'             => $position->status,
            'computed_status'    => $position->computed_status,
            'is_assignable'      => $position->is_assignable,
            'is_filled'          => $position->isFilled(),
            'is_vacant'          => $position->isVacant(),
            'is_unfilled'        => $position->isUnfilled(),
            'requires_approval'  => $position->requires_approval,
            'vacated_at'         => $position->vacated_at?->toIso8601String(),
            'vacancy_notes'      => $position->vacancy_notes,
            'date_of_assumption' => $position->date_of_assumption?->toDateString(),
            'division'           => $position->division?->only(['id', 'name']),
            'department_ids'     => $position->department_ids ?? [],
            'salary_grade_id'    => $position->salary_grade_id,
            'step_increment_id'  => $position->step_increment_id,
            'salary_grade'       => $sg ? [
                'id'             => $sg->id,
                'salary_grade'   => $sg->salary_grade,
                'monthly_salary' => $sg->monthly_salary,
                'annual_salary'  => $sg->annual_salary,
            ] : null,
            'step_increment'     => $step ? [
                'id'             => $step->id,
                'step'           => $step->step,
                'monthly_salary' => $step->monthly_salary,
                'annual_salary'  => $step->annual_salary,
            ] : null,
            'has_salary_config'  => $position->has_salary_config,
            'salary_info'        => $position->formatSalaryInfo(),
            'active_assignments' => $activeAssignments
                ->map(fn($a) => [
                    'id'            => $a->id,
                    'employee_id'   => $a->employee_id,
                    'employee_name' => $a->employee?->full_name,
                    'start_date'    => $a->start_date,
                    'end_date'      => $a->end_date,
                ])
                ->toArray(),
            'is_deleted'  => $position->trashed(),
            'deleted_at'  => $position->deleted_at?->toIso8601String(),
            'created_at'  => $position->created_at?->toIso8601String(),
            'updated_at'  => $position->updated_at?->toIso8601String(),
        ];
    }
}