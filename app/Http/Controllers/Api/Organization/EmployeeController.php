<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Organization\EmployeeQueryService;
use App\Services\Organization\EmployeeWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeQueryService $query,
        private readonly EmployeeWriteService $writer,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:employees.view')->only(['index', 'show', 'nonRoot', 'genderCount', 'avatar', 'pendingPlantillaProvision']);
        $this->middleware('permission:employees.manage')->only(['store', 'update', 'destroy']);
        $this->middleware('permission:team.view')->only(['team']);
    }

    // ─── GenderCount ─────────────────────────────────────────────────────
    public function genderCount(): JsonResponse
    {
        return response()->json($this->query->genderCount());
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->query->index($request));
    }

    // ─── PendingPlantillaProvision ────────────────────────────────────────────────
    public function pendingPlantillaProvision(Employee $employee): JsonResponse
    {
        return response()->json($this->query->pendingPlantillaProvision($employee));
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Employee $employee): JsonResponse
    {
        return response()->json($this->query->show($employee));
    }

    // ─── Team ─────────────────────────────────────────────────────
    public function team(): JsonResponse
    {
        return response()->json($this->query->team());
    }

    // ─── NonRoot ─────────────────────────────────────────────────────
    public function nonRoot(): JsonResponse
    {
        return response()->json($this->query->nonRoot());
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        return response()->json($this->writer->store($request), 201);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Employee $employee): JsonResponse
    {
        return response()->json($this->writer->update($request, $employee));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $result = $this->writer->destroy($request, $employee);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Avatar ─────────────────────────────────────────────────────
    public function avatar(Request $request, Employee $employee): JsonResponse
    {
        return response()->json(['url' => $this->writer->avatarUrl($employee, $request)]);
    }
}
