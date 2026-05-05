<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlantillaItem;
use App\Models\PlantillaPosition;
use Illuminate\Http\Request;

class PlantillaItemController extends Controller
{
    // ─── Index ────────────────────────────────────────────────────────────

    public function index()
    {
        return PlantillaItem::with([
            'division',
            'positions.salaryGrade',
            'positions.stepIncrement',
            'positions.activeAssignments',
        ])
        ->withCount([
            'positions',
            'positions as filled_count'   => fn ($q) => $q->filled(),
            'positions as vacant_count'   => fn ($q) => $q->vacant(),
            'positions as unfilled_count' => fn ($q) => $q->unfilled(),
        ])
        ->orderByRaw('CAST(base_item_number AS UNSIGNED)')
        ->get()
        ->map(fn (PlantillaItem $item) => $this->format($item));
    }

    // ─── Store ────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'base_item_number' => ['required', 'string', 'max:20'],
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'approved_slots'   => ['required', 'integer', 'min:1', 'max:99'],
            'division_id'      => ['nullable', 'exists:divisions,id'],
            'department_ids'   => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
        ]);

        $item = PlantillaItem::create($data);

        $this->provisionSlots($item, $data['approved_slots']);

        return response()->json(
            $this->format($item->fresh([
                'division',
                'positions.salaryGrade',
                'positions.stepIncrement',
                'positions.activeAssignments',
            ])),
            201
        );
    }

    // ─── Show ─────────────────────────────────────────────────────────────

    public function show(PlantillaItem $plantillaItem)
    {
        $plantillaItem->load([
            'division',
            'positions.salaryGrade',
            'positions.stepIncrement',
            'positions.activeAssignments.employee:id,first_name,last_name,prefix,suffix',
        ]);

        return response()->json($this->format($plantillaItem));
    }

    // ─── Update ───────────────────────────────────────────────────────────

    public function update(Request $request, PlantillaItem $plantillaItem)
    {
        $data = $request->validate([
            'base_item_number' => ['sometimes', 'string', 'max:20'],
            'title'            => ['sometimes', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'approved_slots'   => ['sometimes', 'integer', 'min:1', 'max:99'],
            'division_id'      => ['nullable', 'exists:divisions,id'],
            'department_ids'   => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
        ]);

        if (isset($data['approved_slots'])) {
            $currentCount = $plantillaItem->positions()->count();
            if ($data['approved_slots'] < $currentCount) {
                return response()->json([
                    'message' => "Cannot reduce approved slots to {$data['approved_slots']}. " .
                                 "There are already {$currentCount} position slot(s) under this item.",
                ], 422);
            }
        }

        $oldSlots = $plantillaItem->approved_slots;
        $plantillaItem->update($data);

        if (isset($data['approved_slots']) && $data['approved_slots'] > $oldSlots) {
            $this->provisionSlots($plantillaItem, $data['approved_slots'] - $oldSlots);
        }

        return response()->json(
            $this->format($plantillaItem->fresh([
                'division',
                'positions.salaryGrade',
                'positions.stepIncrement',
            ]))
        );
    }

    // ─── Destroy ──────────────────────────────────────────────────────────

    public function destroy(PlantillaItem $plantillaItem)
    {
        if ($plantillaItem->positions()->filled()->exists()) {
            return response()->json([
                'message' => 'Cannot delete an item that still has filled slots.',
            ], 422);
        }

        if ($plantillaItem->positions()->unfilled()->exists()) {
            return response()->json([
                'message' => 'Cannot delete an item that has slots awaiting approval.',
            ], 422);
        }

        $plantillaItem->delete();

        return response()->json(['message' => 'Plantilla item deleted.']);
    }

    // ─── Summary ──────────────────────────────────────────────────────────

    public function summary()
    {
        return response()->json([
            'total_items' => PlantillaItem::count(),
            'total_slots' => PlantillaPosition::count(),
            'filled'      => PlantillaPosition::filled()->count(),
            'vacant'      => PlantillaPosition::vacant()->count(),
            'unfilled'    => PlantillaPosition::unfilled()->count(),
        ]);
    }

    // ─── Available ────────────────────────────────────────────────────────

    public function available()
    {
        return PlantillaItem::with('division')
            ->withCount('positions')
            ->orderByRaw('CAST(base_item_number AS UNSIGNED)')
            ->get()
            ->filter(fn ($item) => $item->approved_slots > $item->positions_count)
            ->map(fn ($item) => [
                'id'               => $item->id,
                'base_item_number' => $item->base_item_number,
                'title'            => $item->title,
                'approved_slots'   => $item->approved_slots,
                'used_slots'       => $item->positions_count,
                'available_slots'  => $item->approved_slots - $item->positions_count,
                'division'         => $item->division?->only(['id', 'name']),
                'department_ids'   => $item->department_ids ?? [],
            ])
            ->values();
    }

    // ─── Steps by Salary Grade ────────────────────────────────────────────

    public function stepsBySalaryGrade(int $salaryGradeId)
    {
        return \App\Models\StepIncrement::where('salary_grade_id', $salaryGradeId)
            ->orderBy('step')
            ->get(['id', 'step', 'monthly_salary', 'annual_salary']);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────

    private function format(PlantillaItem $item): array
    {
        $data = $item->toArray();

        $data['remaining_slots']     = $item->remainingSlots();
        $data['available_slots']     = $item->remainingSlots();
        $data['has_available_slots'] = $item->hasAvailableSlots();

        if ($item->relationLoaded('positions')) {
            $data['positions'] = $item->positions->map(
                fn (PlantillaPosition $pos) => array_merge($pos->toArray(), [
                    'computed_status' => $pos->computed_status,
                    'is_assignable'   => $pos->isAssignable(),
                ])
            )->toArray();
        }

        return $data;
    }

    private function provisionSlots(PlantillaItem $item, int $count): void
    {
        $existing = $item->positions()->count();
        $startIdx = $existing + 1;

        for ($i = 0; $i < $count; $i++) {
            $suffix     = $startIdx + $i;
            $itemNumber = $item->approved_slots === 1
                ? $item->base_item_number
                : $item->base_item_number . '-' . $suffix;

            PlantillaPosition::create([
                'plantilla_item_id' => $item->id,
                'item_number'       => $itemNumber,
                'position_title'    => null,
                'salary_grade_id'   => null,
                'step_increment_id' => null,
                'status'            => 'VACANT',
                'requires_approval' => false,
            ]);
        }
    }
}