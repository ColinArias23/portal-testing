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

    public function vacantCount()
    {
        /**
         * ✅ Correct Vacant logic:
         * Plantilla items with NO assigned employee.
         */
        $count = PlantillaItem::query()
            ->whereDoesntHave('employee')
            ->count();

        return response()->json(['count' => $count]);
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
            ->whereDoesntHave('employee')
            ->count();

        return response()->json([
            'plantilla'  => $plantilla,
            'cos'        => $cos,
            'consultant' => $consultant,
            'vacant'     => $vacant,
            'total'      => $plantilla + $cos + $consultant + $vacant,
        ]);
    }
}
