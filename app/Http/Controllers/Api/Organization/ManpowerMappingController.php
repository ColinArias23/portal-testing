<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Services\Organization\ManpowerMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManpowerMappingController extends Controller
{
    public function __construct(
        private readonly ManpowerMappingService $manpower,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:manpower.view')->only(['departments', 'departmentDetail', 'summary', 'plantillaCount', 'cosCount', 'consultantCount', 'vacantCount', 'tree', 'report', 'overstaffed', 'divisionAnalytics',]);
    }

    // ─── Department ─────────────────────────────────────────────────────
    public function departments(Request $request): JsonResponse
    {
        return response()->json($this->manpower->departments($request));
    }

    // ─── DepartmentDetail ─────────────────────────────────────────────────────
    public function departmentDetail($departmentId): JsonResponse
    {
        return response()->json($this->manpower->departmentDetail($departmentId));
    }

    // ─── Summary ─────────────────────────────────────────────────────
    public function summary(): JsonResponse
    {
        return response()->json($this->manpower->summary());
    }

    // ─── PlantillaCount ─────────────────────────────────────────────────────
    public function plantillaCount(): JsonResponse
    {
        return response()->json(['count' => $this->manpower->plantillaCount()]);
    }

    // ─── CosCount ─────────────────────────────────────────────────────
    public function cosCount(): JsonResponse
    {
        return response()->json(['count' => $this->manpower->cosCount()]);
    }

    // ─── ConsultCount ─────────────────────────────────────────────────────
    public function consultantCount(): JsonResponse
    {
        return response()->json(['count' => $this->manpower->consultantCount()]);
    }

    // ─── VacantCount ─────────────────────────────────────────────────────
    public function vacantCount(): JsonResponse
    {
        return response()->json(['count' => $this->manpower->vacantCount()]);
    }

    // ─── Tree ─────────────────────────────────────────────────────
    public function tree(Request $request): JsonResponse
    {
        return response()->json($this->manpower->tree($request));
    }

    // ─── Report ─────────────────────────────────────────────────────
    public function report(Request $request): JsonResponse
    {
        return response()->json($this->manpower->report($request));
    }

    // ─── OverStaffed ─────────────────────────────────────────────────────
    public function overstaffed(): JsonResponse
    {
        return response()->json($this->manpower->overstaffed());
    }

    // ─── DivisionAnalytics ─────────────────────────────────────────────────────
    public function divisionAnalytics(): JsonResponse
    {
        return response()->json($this->manpower->divisionAnalytics());
    }
}