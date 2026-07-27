<?php

namespace App\Http\Controllers\Api\Skills;

use App\Http\Controllers\Controller;
use App\Models\DevelopmentPlan;
use App\Models\Employee;
use App\Services\Skills\DevelopmentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevelopmentPlanController extends Controller
{
    public function __construct(
        private readonly DevelopmentPlanService $plans,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:trainings.view')->only(['index', 'show', 'byEmployee']);
        $this->middleware('permission:trainings.manage')->only(['store', 'update', 'updateProgress', 'complete', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->plans->index($request));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'         => ['required', 'exists:employees,id'],
            'skill_assessment_id' => ['required', 'exists:skill_assessments,id'],
            'skill_id'            => ['required', 'exists:skills,id'],
            'goal'                => ['nullable', 'string'],
            'actions'             => ['nullable', 'array'],
            'resources'           => ['nullable', 'array'],
            'start_date'          => ['required', 'date'],
            'target_date'         => ['required', 'date', 'after:start_date'],
            'status'              => ['nullable', 'in:planned,in_progress,completed,paused'],
        ]);

        return response()->json([
            'message' => 'Development plan created.',
            'data'    => $this->plans->store($validated),
        ], 201);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(DevelopmentPlan $plan): JsonResponse
    {
        return response()->json(['data' => $this->plans->show($plan)]);
    }

    // ─── ByEmployee ─────────────────────────────────────────────────────
    public function byEmployee(Employee $employee): JsonResponse
    {
        return response()->json(['data' => $this->plans->byEmployee($employee)]);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, DevelopmentPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'goal'                => ['nullable', 'string'],
            'actions'             => ['nullable', 'array'],
            'resources'           => ['nullable', 'array'],
            'start_date'          => ['sometimes', 'date'],
            'target_date'         => ['sometimes', 'date'],
            'status'              => ['sometimes', 'in:planned,in_progress,completed,paused'],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        return response()->json([
            'message' => 'Development plan updated.',
            'data'    => $this->plans->update($plan, $validated),
        ]);
    }

    // ─── UpdateProgress ─────────────────────────────────────────────────────
    public function updateProgress(Request $request, DevelopmentPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'notes'               => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Progress updated.',
            'data'    => $this->plans->updateProgress(
                $plan,
                $validated['progress_percentage'],
                $request->filled('notes') ? $validated['notes'] : null
            ),
        ]);
    }

    // ─── Complete ─────────────────────────────────────────────────────
    public function complete(Request $request, DevelopmentPlan $plan): JsonResponse
    {
        return response()->json([
            'message' => 'Development plan completed.',
            'data'    => $this->plans->complete($plan),
        ]);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(DevelopmentPlan $plan): JsonResponse
    {
        $this->plans->destroy($plan);

        return response()->json(['message' => 'Development plan deleted.']);
    }
}
