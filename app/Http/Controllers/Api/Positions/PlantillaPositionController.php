<?php

namespace App\Http\Controllers\Api\Positions;

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
        $this->middleware('permission:plantilla_items.view')->only(['index', 'show', 'steps', 'summary', 'salaryConfig', 'availableItems']);
        $this->middleware('permission:plantilla_items.manage')->only([ 'store', 'addSlots', 'update', 'destroy', 'bulkDelete', 'restore', 'bulkRestore', 'approve']);
    }
// ─── Index ─────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = PlantillaPosition::query()
            ->with([
                'displayDepartment:id,name,code',
                'displayDivision:id,name,code',
                'salaryGrade',
                'stepIncrement',
                'activeAssignments.employee:id,prefix,first_name,middle_name,last_name,suffix,title',
            ])
            ->orderBy('base_item_number')
            ->orderBy('slot_number');

        if ($request->filled('base_item_number')) {
            $query->where('base_item_number', $request->base_item_number);
        }
        if ($request->filled('department_id')) {
            $query->forDepartment((int) $request->department_id);
        }
        if ($request->filled('display_department_id')) {
            $query->where('display_department_id', $request->display_department_id);
        }
        if ($request->filled('status')) {
            match (strtoupper($request->status)) {
                'VACANT'   => $query->vacant(),
                'FILLED'   => $query->filled(),
                'UNFILLED' => $query->unfilled(),
                default    => null,
            }; 
        }
        if ($request->filled('salary_grade_id')) {
            $query->where('salary_grade_id', $request->salary_grade_id);
        }
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
// ─── Show ──────────────────────────────────────────────────────────────
    public function show(PlantillaPosition $plantillaPosition)
    {
        $plantillaPosition->load([
            'displayDepartment:id,name,code',
            'displayDivision:id,name,code',
            'salaryGrade.stepIncrements',
            'stepIncrement',
            'activeAssignments.employee',
        ]);

        return response()->json($this->format($plantillaPosition));
    }
// ─── Steps ─────────────────────────────────────────────────────────────
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
// ─── Store ─────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'base_item_number'      => ['required', 'string', 'max:20'],
            'title'                 => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'slots'                 => ['required', 'integer', 'min:1', 'max:99'],
            'display_department_id' => ['nullable', 'integer', 'exists:departments,id', 'prohibits:display_division_id'],
            'display_division_id'   => ['nullable', 'integer', 'exists:divisions,id', 'prohibits:display_department_id'],
            'role'                  => ['nullable', 'string', 'max:100'],
            'salary_grade_id'       => ['nullable', 'integer', 'exists:salary_grades,id'],
            'step_increment_id'     => ['nullable', 'integer', 'exists:step_increments,id'],
        ]);

        $slots = $data['slots'];
        unset($data['slots']);

        DB::transaction(function () use ($data, $slots, $request) {
            PlantillaPosition::onlyTrashed()
                ->where('base_item_number', $data['base_item_number'])
                ->forceDelete();

            for ($i = 1; $i <= $slots; $i++) {
                PlantillaPosition::create([
                    ...$data,
                    'slot_number'        => $i,
                    'position_slot_name' => $this->buildSlotName($data['base_item_number'], $i, $slots),
                    'position_title'     => $data['title'],
                    'status'             => 'VACANT',
                    'requires_approval'  => false,
                ]);
            }

            ActivityLog::record(
                action: 'plantilla_position.batch_created',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                subjectLabel: "{$data['title']} ({$slots} position" . ($slots > 1 ? 's' : '') . ")",
                payload: [
                    'base_item_number'      => $data['base_item_number'],
                    'title'                 => $data['title'],
                    'slots'                 => $slots,
                    'display_department_id' => $data['display_department_id'] ?? null,
                    'display_division_id'   => $data['display_division_id'] ?? null,
                    'role'                  => $data['role'] ?? null,
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        $positions = PlantillaPosition::where('base_item_number', $data['base_item_number'])
            ->with(['displayDepartment', 'displayDivision', 'salaryGrade', 'stepIncrement'])
            ->get();

        return response()->json([
            'message'   => "Created {$slots} position" . ($slots > 1 ? 's' : '') . " for {$data['title']}.",
            'positions' => $positions->map(fn(PlantillaPosition $pos) => $this->format($pos)),
        ], 201);
    }
// ─── AddSlots ─────────────────────────────────────────────────────────
    public function addSlots(Request $request)
    {
        $data = $request->validate([
            'base_item_number' => ['required', 'string', 'exists:plantilla_positions'],
            'count'            => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $highest  = PlantillaPosition::where('base_item_number', $data['base_item_number'])->max('slot_number') ?? 0;
        $template = PlantillaPosition::where('base_item_number', $data['base_item_number'])->first();

        if (!$template) {
            return response()->json(['message' => 'Plantilla item not found.'], 404);
        }
        $totalSlotsAfterAdd = $highest + $data['count'];

        DB::transaction(function () use ($template, $data, $highest, $totalSlotsAfterAdd, $request) {
            PlantillaPosition::onlyTrashed()
                ->where('base_item_number', $template->base_item_number)
                ->forceDelete();

            for ($i = 1; $i <= $data['count']; $i++) {
                $newSlotNumber = $highest + $i;

                PlantillaPosition::create([
                    'base_item_number'      => $template->base_item_number,
                    'title'                 => $template->title,
                    'position_title'        => $template->title,
                    'description'           => $template->description,
                    'slot_number'           => $newSlotNumber,
                    'position_slot_name'    => $this->buildSlotName($template->base_item_number, $newSlotNumber, $totalSlotsAfterAdd),
                    'display_department_id' => $template->display_department_id,
                    'display_division_id'   => $template->display_division_id,
                    'role'                  => $template->role,
                    'salary_grade_id'       => $template->salary_grade_id,
                    'step_increment_id'     => $template->step_increment_id,
                    'status'                => 'VACANT',
                    'requires_approval'     => false,
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
// ─── Update ────────────────────────────────────────────────────────────
    public function update(Request $request, PlantillaPosition $plantillaPosition)
    {
        $data = $request->validate([
            'position_title'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'role'                  => ['sometimes', 'nullable', 'string', 'max:100'],
            'display_department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id', 'prohibits:display_division_id'],
            'display_division_id'   => ['sometimes', 'nullable', 'integer', 'exists:divisions,id', 'prohibits:display_department_id'],
            'salary_grade_id'       => ['sometimes', 'nullable', 'integer', 'exists:salary_grades,id'],
            'step_increment_id'     => ['sometimes', 'nullable', 'integer', 'exists:step_increments,id'],
            'date_of_assumption'    => ['sometimes', 'nullable', 'date'],
            'vacancy_notes'         => ['sometimes', 'nullable', 'string'],
            'requires_approval'     => ['sometimes', 'boolean'],
        ]);

        $before = [
            'salary_grade_id'       => $plantillaPosition->salary_grade_id,
            'step_increment_id'     => $plantillaPosition->step_increment_id,
            'position_title'        => $plantillaPosition->position_title,
            'role'                  => $plantillaPosition->role,
            'display_department_id' => $plantillaPosition->display_department_id,
            'display_division_id'   => $plantillaPosition->display_division_id,
        ];

        if (isset($data['requires_approval'])) {
            $data['status']     = $data['requires_approval'] ? 'UNFILLED' : 'VACANT';
            $data['vacated_at'] = $data['requires_approval'] ? now() : null;
        }

        if ($plantillaPosition->isFilled()) {
            unset($data['role'], $data['display_department_id'], $data['display_division_id']);
        }

        if (array_key_exists('display_department_id', $data) && !empty($data['display_department_id'])) {
            $data['display_division_id'] = null;
        } elseif (array_key_exists('display_division_id', $data) && !empty($data['display_division_id'])) {
            $data['display_department_id'] = null;
        }

        $plantillaPosition->update($data);

        ActivityLog::record(
            action: 'plantilla_position.updated',
            userId: $request->user()?->id,
            subjectType: PlantillaPosition::class,
            subjectId: $plantillaPosition->id,
            subjectLabel: $plantillaPosition->position_slot_name,
            payload: ['before' => $before, 'after' => [
                'salary_grade_id'       => $plantillaPosition->salary_grade_id,
                'step_increment_id'     => $plantillaPosition->step_increment_id,
                'position_title'        => $plantillaPosition->position_title,
                'role'                  => $plantillaPosition->role,
                'display_department_id' => $plantillaPosition->display_department_id,
                'display_division_id'   => $plantillaPosition->display_division_id,
            ]],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(
            $this->format($plantillaPosition->fresh(['displayDepartment', 'displayDivision', 'salaryGrade', 'stepIncrement']))
        );
    }
// ─── Destroy ───────────────────────────────────────────────────────────
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
// ─── BulkDelete ───────────────────────────────────────────────────────
    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:plantilla_positions,id'],
        ]);

        $positions  = PlantillaPosition::whereIn('id', $data['ids'])->get();
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
                payload: ['count' => $positions->count(), 'positions' => $positions->pluck('position_slot_name')],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return response()->json([
            'message' => "Deleted {$positions->count()} position slot(s).",
            'count'   => $positions->count(),
        ]);
    }
// ─── Restore ───────────────────────────────────────────────────────────
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
            payload: ['base_item_number' => $position->base_item_number, 'slot_number' => $position->slot_number],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );
        return response()->json([
            'message'  => 'Position restored.',
            'position' => $this->format($position->fresh(['displayDepartment', 'displayDivision', 'salaryGrade', 'stepIncrement'])),
        ]);
    }
// ─── BulkRestore ──────────────────────────────────────────────────────
    public function bulkRestore(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $positions = PlantillaPosition::onlyTrashed()->whereIn('id', $data['ids'])->get();
        if ($positions->isEmpty()) {
            return response()->json(['message' => 'No deleted positions found with those IDs.'], 404);
        }

        DB::transaction(function () use ($positions, $request) {
            $positions->each(fn($pos) => $pos->restore());
            ActivityLog::record(
                action: 'plantilla_position.bulk_restored',
                userId: $request->user()?->id,
                subjectType: PlantillaPosition::class,
                payload: ['count' => $positions->count(), 'positions' => $positions->pluck('position_slot_name')],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return response()->json([
            'message' => "Restored {$positions->count()} position slot(s).",
            'count'   => $positions->count(),
        ]);
    }
// ─── Approve ───────────────────────────────────────────────────────────
    public function approve(Request $request, PlantillaPosition $plantillaPosition)
    {
        if (!$plantillaPosition->requires_approval) {
            return response()->json(['message' => 'This position does not require approval.'], 422);
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
            'position' => $this->format($plantillaPosition->fresh(['displayDepartment', 'displayDivision', 'salaryGrade', 'stepIncrement'])),
        ]);
    }
// ─── Summary ───────────────────────────────────────────────────────────
    public function summary()
    {
        return response()->json([
            'total_positions' => PlantillaPosition::count(),
            'filled'          => PlantillaPosition::filled()->count(),
            'vacant'          => PlantillaPosition::vacant()->count(),
            'unfilled'        => PlantillaPosition::unfilled()->count(),
            'overstaffed'     => PlantillaPosition::overstaffed()->count(),
            'deleted'         => PlantillaPosition::onlyTrashed()->count(),
            'by_department'   => PlantillaPosition::groupBy('display_department_id')
                ->selectRaw('display_department_id, count(*) as count')
                ->with('displayDepartment:id,name')
                ->get()
                ->map(fn($item) => [
                    'department_id' => $item->display_department_id,
                    'department'    => $item->displayDepartment?->name,
                    'count'         => $item->count,
                ]),
        ]);
    }
// ─── SalaryConfig ─────────────────────────────────────────────────────
    public function salaryConfig(PlantillaPosition $plantillaPosition)
    {
        $plantillaPosition->load(['salaryGrade.stepIncrements', 'stepIncrement']);
        return response()->json($plantillaPosition->formatSalaryInfo());
    }
// ─── AvailableItems ───────────────────────────────────────────────────
    public function availableItems()
    {
        $items = PlantillaPosition::selectRaw('DISTINCT base_item_number, title, role, display_department_id, display_division_id')
            ->with(['displayDepartment:id,name,code', 'displayDivision:id,name,code'])
            ->vacant()
            ->get()
            ->unique('base_item_number')
            ->map(fn($pos) => [
                'base_item_number' => $pos->base_item_number,
                'title'            => $pos->title,
                'role'             => $pos->role,
                'department'       => $pos->displayDepartment
                    ? $pos->displayDepartment->only(['id', 'name', 'code'])
                    : null,
                'division'         => $pos->displayDivision
                    ? $pos->displayDivision->only(['id', 'name', 'code'])
                    : null,
                'available_count'  => PlantillaPosition::where('base_item_number', $pos->base_item_number)
                    ->vacant()
                    ->count(),
            ])
            ->values();

        return response()->json($items);
    }
    private function buildSlotName(string $baseItemNumber, int $slotNumber, int $totalSlots): string
    {
        return $totalSlots === 1
            ? $baseItemNumber
            : "{$baseItemNumber}-{$slotNumber}";
    }
// ─── Format ───────────────────────────────────────────────────
    private function format(PlantillaPosition $position): array
    {
        $activeAssignments = $position->relationLoaded('activeAssignments')
            ? $position->activeAssignments
            : $position->activeAssignments()->get();

        $sg   = $position->relationLoaded('salaryGrade')        ? $position->salaryGrade        : $position->salaryGrade()->first();
        $step = $position->relationLoaded('stepIncrement')       ? $position->stepIncrement       : $position->stepIncrement()->first();
        $dept = $position->relationLoaded('displayDepartment')   ? $position->displayDepartment   : $position->displayDepartment()->first();
        $div  = $position->relationLoaded('displayDivision')     ? $position->displayDivision     : $position->displayDivision()->first();

        return [
            'id'                    => $position->id,
            'base_item_number'      => $position->base_item_number,
            'title'                 => $position->title,
            'description'           => $position->description,
            'slot_number'           => $position->slot_number,
            'position_slot_name'    => $position->position_slot_name,
            'position_title'        => $position->position_title,
            'role'                  => $position->role,
            'display_department_id' => $position->display_department_id,
            'display_department'    => $dept ? $dept->only(['id', 'name', 'code']) : null,
            'display_division_id'  => $position->display_division_id,
            'display_division'      => $div ? $div->only(['id', 'name', 'code']) : null,
            'status'                => $position->status,
            'computed_status'       => $position->computed_status,
            'is_assignable'         => $position->is_assignable,
            'is_filled'             => $position->isFilled(),
            'is_vacant'             => $position->isVacant(),
            'is_unfilled'           => $position->isUnfilled(),
            'requires_approval'     => $position->requires_approval,
            'vacated_at'            => $position->vacated_at?->toIso8601String(),
            'vacancy_notes'         => $position->vacancy_notes,
            'date_of_assumption'    => $position->date_of_assumption?->toDateString(),
            'salary_grade_id'       => $position->salary_grade_id,
            'step_increment_id'     => $position->step_increment_id,
            'salary_grade'          => $sg ? [
                'id'             => $sg->id,
                'salary_grade'   => $sg->salary_grade,
                'monthly_salary' => $sg->monthly_salary,
                'annual_salary'  => $sg->annual_salary,
            ] : null,
            'step_increment'        => $step ? [
                'id'             => $step->id,
                'step'           => $step->step,
                'monthly_salary' => $step->monthly_salary,
                'annual_salary'  => $step->annual_salary,
            ] : null,
            'has_salary_config'     => $position->has_salary_config,
            'salary_info'           => $position->formatSalaryInfo(),
            'active_assignments'    => $activeAssignments->map(fn($a) => [
                'id'            => $a->id,
                'employee_id'   => $a->employee_id,
                'employee_name' => $a->employee?->full_name,
                'employee'      => $a->employee ? [
                    'id'          => $a->employee->id,
                    'prefix'      => $a->employee->prefix,
                    'first_name'  => $a->employee->first_name,
                    'middle_name' => $a->employee->middle_name,
                    'last_name'   => $a->employee->last_name,
                    'suffix'      => $a->employee->suffix,
                    'title'       => $a->employee->title,
                ] : null,
                'start_date'    => $a->start_date,
                'end_date'      => $a->end_date,
            ])->toArray(),
            'is_deleted'            => $position->trashed(),
            'deleted_at'            => $position->deleted_at?->toIso8601String(),
            'created_at'            => $position->created_at?->toIso8601String(),
            'updated_at'            => $position->updated_at?->toIso8601String(),
        ];
    }
}