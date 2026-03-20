<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PlantillaItem;
use App\Models\Division;

class ManpowerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function employeeByType(string $needle)
    {
        return Employee::query()
            ->whereNotNull('employment_type')
            ->whereRaw('LOWER(employment_type) LIKE ?', ['%' . strtolower($needle) . '%']);
    }

    private function cosQuery()
    {
        return Employee::query()
            ->whereNotNull('employment_type')
            ->where(function ($q) {
                $q->whereRaw('LOWER(employment_type) LIKE ?', ['%cos%'])
                  ->orWhereRaw('LOWER(employment_type) LIKE ?', ['%contract%']);
            });
    }

    // Vacant = plantilla items with no ACTIVE assignment
    private function vacantQuery()
    {
        return PlantillaItem::whereDoesntHave('assignments', fn ($q) => $q->active());
    }

    /*
    |--------------------------------------------------------------------------
    | INDIVIDUAL COUNTS
    |--------------------------------------------------------------------------
    */

    public function plantillaCount()
    {
        return response()->json([
            'count' => $this->employeeByType('plantilla')->count(),
        ]);
    }

    public function cosCount()
    {
        return response()->json([
            'count' => $this->cosQuery()->count(),
        ]);
    }

    public function consultantCount()
    {
        return response()->json([
            'count' => $this->employeeByType('consultant')->count(),
        ]);
    }

    public function vacantCount()
    {
        return response()->json([
            'count' => $this->vacantQuery()->count(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    public function summary()
    {
        $plantilla  = $this->employeeByType('plantilla')->count();
        $cos        = $this->cosQuery()->count();
        $consultant = $this->employeeByType('consultant')->count();
        $vacant     = $this->vacantQuery()->count();

        return response()->json([
            'plantilla'  => $plantilla,
            'cos'        => $cos,
            'consultant' => $consultant,
            'vacant'     => $vacant,
            'total'      => $plantilla + $cos + $consultant,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | OVERSTAFFED
    |--------------------------------------------------------------------------
    */

    public function overstaffed()
    {
        $items = PlantillaItem::withCount([
            'assignments as active_count' => fn ($q) => $q->active(),
        ])
        ->having('active_count', '>', 1)
        ->get();

        return response()->json([
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DIVISION ANALYTICS
    |--------------------------------------------------------------------------
    */

    public function divisionAnalytics()
    {
        $divisions = Division::with([
            'plantillaItems' => function ($q) {
                $q->withCount([
                    'assignments as active_count' => fn ($qq) => $qq->active(),
                ]);
            },
        ])->get();

        $result = $divisions->map(function ($division) {
            $total       = $division->plantillaItems->count();
            $filled      = $division->plantillaItems->where('active_count', '>=', 1)->count();
            $overstaffed = $division->plantillaItems->where('active_count', '>', 1)->count();
            $vacant      = $total - $filled;

            return [
                'division_id'   => $division->id,
                'division_name' => $division->name,
                'total_slots'   => $total,
                'filled'        => $filled,
                'vacant'        => $vacant,
                'overstaffed'   => $overstaffed,
            ];
        });

        return response()->json($result);
    }
}