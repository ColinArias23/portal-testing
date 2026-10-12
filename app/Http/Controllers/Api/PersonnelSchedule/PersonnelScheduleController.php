<?php

namespace App\Http\Controllers\Api\PersonnelSchedule;

use App\Http\Controllers\Controller;
use App\Models\PschedLine;
use App\Models\PschedSlot;
use App\Services\PersonnelSchedule\PersonnelScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonnelScheduleController extends Controller
{
    public function __construct(
        private readonly PersonnelScheduleService $schedule,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:plantilla_items.view')->only(['index']);
        $this->middleware('permission:plantilla_items.manage')->only(['employeeOptions', 'assign', 'unassign', 'updateLine']);
    }

    public function index(): JsonResponse
    {
        return response()->json($this->schedule->snapshot());
    }

    public function employeeOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(['data' => $this->schedule->searchEmployees($data['search'] ?? null)]);
    }

    public function updateLine(Request $request, PschedLine $line): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'],
            'slots' => ['required', 'array', 'min:1', 'max:99'],
            'slots.*.id' => ['nullable', 'integer'],
            'slots.*.item_number' => ['nullable', 'string', 'max:20'],
        ]);

        return response()->json(['data' => $this->schedule->updateLine($line, $data)]);
    }

    public function assign(Request $request, PschedSlot $slot): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);

        return response()->json([
            'data' => $this->schedule->assign($slot, (int) $data['employee_id'], $request->user()?->id),
        ]);
    }

    public function unassign(PschedSlot $slot): JsonResponse
    {
        return response()->json(['data' => $this->schedule->unassign($slot)]);
    }
}
