<?php

namespace App\Http\Controllers\Api\Skills;

use App\Http\Controllers\Controller;
use App\Models\SkillAssessment;
use App\Models\Employee;
use App\Services\Skills\SkillGapAnalysisService;
use Illuminate\Http\JsonResponse;

class SkillGapAnalysisController extends Controller
{
    protected SkillGapAnalysisService $analysisService;

    public function __construct(SkillGapAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;

        $this->middleware('auth:sanctum');
        $this->middleware('permission:trainings.view')->only(['generate', 'departmentStats', 'employeeSummary', 'export']);
        $this->middleware('permission:trainings.manage')->only(['createPlans']);
    }

// ─── Generate ─────────────────────────────────────────────────────
    public function generate(SkillAssessment $assessment): JsonResponse
    {
        $report = $this->analysisService->generateReport($assessment);

        return response()->json([
            'data' => $report,
        ]);
    }
// ─── CreatePlans ─────────────────────────────────────────────────────
    public function createPlans(SkillAssessment $assessment): JsonResponse
    {
        $plans = $this->analysisService->createDevelopmentPlans($assessment);

        return response()->json([
            'message' => "Created {$plans->count()} development plans.",
            'data'    => $plans,
        ], 201);
    }
// ─── DepartmentStats ─────────────────────────────────────────────────────
    public function departmentStats($departmentId): JsonResponse
    {
        $stats = $this->analysisService->getDepartmentStats($departmentId);

        return response()->json([
            'data' => $stats,
        ]);
    }
// ─── EmployeeSummary ─────────────────────────────────────────────────────
    public function employeeSummary(Employee $employee): JsonResponse
    {
        $latestAssessment = $employee->skillAssessments()
            ->where('status', SkillAssessment::STATUS_DONE)
            ->latest('submitted_at')
            ->first();

        if (! $latestAssessment) {
            return response()->json([
                'message' => 'No completed assessment found.',
                'data'    => null,
            ], 404);
        }

        $report           = $this->analysisService->generateReport($latestAssessment);
        $developmentPlans = $employee->developmentPlans()
            ->whereIn('status', ['planned', 'in_progress'])
            ->with('skill')
            ->get();

        return response()->json([
            'data' => [
                'assessment'        => $latestAssessment,
                'analysis'          => $report,
                'development_plans' => $developmentPlans,
            ],
        ]);
    }
// ─── Export ─────────────────────────────────────────────────────
    public function export(SkillAssessment $assessment): JsonResponse
    {
        $report = $this->analysisService->generateReport($assessment);

        return response()->json([
            'employee_name' => $assessment->employee->full_name ?? 'Unknown',
            'department'    => $assessment->department->name ?? 'N/A',
            'submitted_at'  => $assessment->submitted_at,
            'analysis'      => $report,
        ]);
    }
}