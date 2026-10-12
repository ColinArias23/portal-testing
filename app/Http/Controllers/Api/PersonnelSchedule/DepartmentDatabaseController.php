<?php

namespace App\Http\Controllers\Api\PersonnelSchedule;

use App\Http\Controllers\Controller;
use App\Models\PschedDivision;
use App\Models\PschedUnit;
use App\Services\PersonnelSchedule\DepartmentDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentDatabaseController extends Controller
{
    public function __construct(
        private readonly DepartmentDatabaseService $departments,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:plantilla_items.manage');
    }

    public function storeDivision(Request $request): JsonResponse
    {
        $division = $this->departments->createDivision($this->validateDivision($request));

        return response()->json(['data' => ['id' => $division->id]], 201);
    }

    public function updateDivision(Request $request, PschedDivision $division): JsonResponse
    {
        $this->departments->updateDivision($division, $this->validateDivision($request));

        return response()->json(['data' => ['id' => $division->id]]);
    }

    public function destroyDivision(PschedDivision $division): JsonResponse
    {
        $this->departments->deleteDivision($division);

        return response()->json(['message' => 'Deleted.']);
    }

    public function storeUnit(Request $request): JsonResponse
    {
        $unit = $this->departments->createUnit($this->validateUnit($request));

        return response()->json(['data' => ['id' => $unit->id]], 201);
    }

    public function updateUnit(Request $request, PschedUnit $unit): JsonResponse
    {
        $this->departments->updateUnit($unit, $this->validateUnit($request));

        return response()->json(['data' => ['id' => $unit->id]]);
    }

    public function destroyUnit(PschedUnit $unit): JsonResponse
    {
        $this->departments->deleteUnit($unit);

        return response()->json(['message' => 'Deleted.']);
    }

    private function validateDivision(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:OFFICE,DIRECTORATE,DIVISION'],
            'status' => ['required', 'in:ACTIVE,INACTIVE'],
        ]);
    }

    private function validateUnit(Request $request): array
    {
        return $request->validate([
            'division_id' => ['required', 'integer', 'exists:psched_divisions,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:DEPARTMENT,SECTION,UNIT,CLUSTER'],
            'status' => ['required', 'in:ACTIVE,INACTIVE'],
        ]);
    }
}
