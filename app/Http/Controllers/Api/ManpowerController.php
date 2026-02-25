<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PlantillaItem;

class ManpowerController extends Controller
{
    private function likeEmploymentType(string $needle)
    {
        return Employee::query()
            ->whereNotNull('employment_type')
            ->whereRaw('LOWER(employment_type) LIKE ?', ['%' . strtolower($needle) . '%']);
    }

    public function plantillaCount()
    {
        $count = $this->likeEmploymentType('plantilla')->count();
        return response()->json(['count' => $count]);
    }

    public function cosCount()
    {
        $count = Employee::query()
            ->whereNotNull('employment_type')
            ->where(function ($q) {
                $q->whereRaw('LOWER(employment_type) LIKE ?', ['%cos%'])
                  ->orWhereRaw('LOWER(employment_type) LIKE ?', ['%contract%']);
            })
            ->count();

        return response()->json(['count' => $count]);
    }

    public function consultantCount()
    {
        $count = $this->likeEmploymentType('consultant')->count();
        return response()->json(['count' => $count]);
    }

    /**
     * ✅ Correct Vacant logic:
     * plantilla_items with NO ACTIVE employee_assignment
     */
    public function vacantCount()
    {
        $count = PlantillaItem::whereDoesntHave('assignments')->count();

        return response()->json([
            'count' => $count
        ]);
    }
    public function summary()
    {
        $plantilla = $this->likeEmploymentType('plantilla')->count();

        $cos = Employee::query()
            ->whereNotNull('employment_type')
            ->where(function ($q) {
                $q->whereRaw('LOWER(employment_type) LIKE ?', ['%cos%'])
                  ->orWhereRaw('LOWER(employment_type) LIKE ?', ['%contract%']);
            })
            ->count();

        $consultant = $this->likeEmploymentType('consultant')->count();

        $vacant = PlantillaItem::query()
            ->whereDoesntHave('assignments', fn ($q) => $q->active())
            ->count();

        return response()->json([
            'plantilla'  => $plantilla,
            'cos'        => $cos,
            'consultant' => $consultant,
            'vacant'     => $vacant,
            'total'      => $plantilla + $cos + $consultant + $vacant,
        ]);
    }

    public function overstaffed()
    {
        $items = PlantillaItem::withCount([
            'assignments as active_count' => fn ($q) => $q->active()
        ])
        ->having('active_count', '>', 1)
        ->get();

        return response()->json([
            'count' => $items->count(),
            'items' => $items
        ]);
    }

    public function divisionAnalytics()
    {
        $division = \App\Models\Division::with([
            'plantillaItems' => function ($q) {
                $q->withCount([
                    'assignments as active_count' => fn ($qq) => $qq->active()
                ]);
            }
        ])->get();

        $result = $division->map(function ($division) {

            $total = $division->plantillaItems->count();

            $filled = $division->plantillaItems
                ->where('active_count', '>=', 1)
                ->count();

            $overstaffed = $division->plantillaItems
                ->where('active_count', '>', 1)
                ->count();

            $vacant = $total - $filled;

            return [
                'division_id' => $division->id,
                'division_name' => $division->name,
                'total_slots' => $total,
                'filled' => $filled,
                'vacant' => $vacant,
                'overstaffed' => $overstaffed,
            ];
        });

        return response()->json($result);
    }
}