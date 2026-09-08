<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Models\WorkSuspension;
use App\Services\Leave\WorkSuspensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkSuspensionController extends Controller
{
    public function __construct(private readonly WorkSuspensionService $workSuspensionService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:leave.suspensions.manage');
    }

    public function index(): JsonResponse
    {
        $suspensions = WorkSuspension::with('createdBy:id,username')
            ->orderByDesc('start_date')
            ->get();

        return response()->json(['data' => $suspensions]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $suspension = $this->workSuspensionService->declare($validated, $request->user());

        return response()->json([
            'data' => $suspension,
            'message' => $suspension->affected_leave_requests_count > 0
                ? "Suspension declared. {$suspension->affected_leave_requests_count} employee(s) with overlapping approved leave were notified."
                : 'Suspension declared. No approved leave requests overlap these dates.',
        ], 201);
    }
}