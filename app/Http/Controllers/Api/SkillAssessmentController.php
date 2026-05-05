<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SkillAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillAssessmentController extends Controller
{
    /**
     * GET /api/skill-assessments
     */
    public function index(Request $request): JsonResponse
    {
        $query = SkillAssessment::with(['employee', 'department']);

        if ($request->filled('employee_id')) {
            $query->byEmployee((int) $request->input('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->latestSubmitted()->paginate(50));
    }

    /**
     * POST /api/skill-assessments
     * Creates and immediately submits the assessment in one atomic operation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'scores'      => ['required', 'array', 'min:1'],
        ]);

        $employee = \App\Models\Employee::findOrFail($validated['employee_id']);

        $departmentId = collect($employee->department_ids ?? [])->first();

        if (! $departmentId) {
            return response()->json([
                'message' => 'This employee does not belong to any department.',
            ], 422);
        }

        // Reuse existing pending assessment instead of creating duplicates
        $assessment = SkillAssessment::where('employee_id', $employee->id)
            ->where('status', SkillAssessment::STATUS_PENDING)
            ->first();

        if ($assessment) {
            $assessment->update(['scores' => $validated['scores']]);
        } else {
            // Block if already done
            $existing = SkillAssessment::where('employee_id', $employee->id)
                ->where('status', SkillAssessment::STATUS_DONE)
                ->exists();

            if ($existing) {
                return response()->json([
                    'message' => 'This employee has already completed their assessment.',
                ], 422);
            }

            $assessment = SkillAssessment::create([
                'employee_id'   => $employee->id,
                'department_id' => $departmentId,
                'scores'        => $validated['scores'],
                'status'        => SkillAssessment::STATUS_PENDING,
            ]);
        }

        // Immediately submit
        $assessment->calculateMetrics()->submit();

        return response()->json([
            'message'      => 'Assessment submitted successfully.',
            'status'       => $assessment->status,
            'submitted_at' => $assessment->submitted_at,
            'data'         => $assessment->fresh()->load(['employee', 'department']),
        ], 201);
    }

    /**
     * GET /api/skill-assessments/{id}
     */
    public function show(SkillAssessment $assessment): JsonResponse
    {
        return response()->json([
            'data' => $assessment->load(['employee', 'department']),
        ]);
    }

    /**
     * PUT /api/skill-assessments/{id}
     * Only allowed while status is pending.
     */
    public function update(Request $request, SkillAssessment $assessment): JsonResponse
    {
        if ($assessment->isDone()) {
            return response()->json([
                'message'      => 'This assessment has already been submitted and cannot be modified.',
                'status'       => $assessment->status,
                'submitted_at' => $assessment->submitted_at,
            ], 422);
        }

        $validated = $request->validate([
            'scores'        => ['nullable', 'array'],
            'department_id' => ['sometimes', 'exists:departments,id'],
        ]);

        $assessment->update($validated);

        return response()->json([
            'message' => 'Assessment updated.',
            'data'    => $assessment->fresh(),
        ]);
    }

    /**
     * POST /api/skill-assessments/{id}/submit
     * Submits the assessment and locks it as done.
     */
    public function submit(SkillAssessment $assessment): JsonResponse
    {
        if ($assessment->isDone()) {
            return response()->json([
                'message'      => 'This assessment has already been submitted.',
                'status'       => $assessment->status,
                'submitted_at' => $assessment->submitted_at,
            ], 422);
        }

        if (empty($assessment->scores)) {
            return response()->json([
                'message' => 'Cannot submit an assessment with no scores.',
                'status'  => $assessment->status,
            ], 422);
        }

        $assessment->calculateMetrics()->submit();

        return response()->json([
            'message'      => 'Assessment submitted successfully.',
            'status'       => $assessment->status,
            'submitted_at' => $assessment->submitted_at,
            'data'         => $assessment->fresh()->load(['employee', 'department']),
        ]);
    }

    /**
     * DELETE /api/skill-assessments/{id}
     * Only pending assessments can be deleted.
     */
    public function destroy(SkillAssessment $assessment): JsonResponse
    {
        if ($assessment->isDone()) {
            return response()->json([
                'message' => 'A submitted assessment cannot be deleted.',
                'status'  => $assessment->status,
            ], 422);
        }

        $assessment->delete();

        return response()->json([
            'message' => 'Assessment deleted.',
        ]);
    }
}