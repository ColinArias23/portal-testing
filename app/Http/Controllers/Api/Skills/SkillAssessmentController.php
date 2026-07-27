<?php

namespace App\Http\Controllers\Api\Skills;

use App\Http\Controllers\Controller;
use App\Models\SkillAssessment;
use App\Services\Skills\SkillAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillAssessmentController extends Controller
{
    public function __construct(
        private readonly SkillAssessmentService $assessments,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:trainings.view')->only(['index', 'show']);
        $this->middleware('permission:trainings.manage')->only(['store', 'update', 'submit', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->assessments->index($request));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'scores'      => ['required', 'array', 'min:1'],
        ]);

        $result = $this->assessments->store($validated);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(SkillAssessment $assessment): JsonResponse
    {
        return response()->json(['data' => $this->assessments->show($assessment)]);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, SkillAssessment $assessment): JsonResponse
    {
        $validated = $request->validate([
            'scores'        => ['nullable', 'array'],
            'department_id' => ['sometimes', 'exists:departments,id'],
        ]);

        $result = $this->assessments->update($assessment, $validated);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Submit ─────────────────────────────────────────────────────
    public function submit(SkillAssessment $assessment): JsonResponse
    {
        $result = $this->assessments->submit($assessment);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(SkillAssessment $assessment): JsonResponse
    {
        $result = $this->assessments->destroy($assessment);

        return response()->json($result['body'], $result['status']);
    }
}
