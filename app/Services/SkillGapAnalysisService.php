<?php

namespace App\Services;

use App\Models\SkillAssessment;
use App\Models\DevelopmentPlan;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class SkillGapAnalysisService
{
    /**
     * Generate gap analysis report
     */
    public function generateReport(SkillAssessment $assessment): array
    {
        $skills = Skill::active()->with('recommendation')->get();
        $scores = $assessment->scores ?? [];

        $ratedSkills  = $skills->filter(fn($s) => isset($scores[$s->id]) && $scores[$s->id] > 0);
        $gapSkills    = $ratedSkills->filter(fn($s) => $scores[$s->id] <= 3);
        $strongSkills = $ratedSkills->filter(fn($s) => $scores[$s->id] >= 4);

        $categories       = $skills->pluck('category')->unique();
        $categoryAverages = $categories->mapWithKeys(function ($cat) use ($skills, $scores) {
            $catSkills = $skills->filter(fn($s) => $s->category === $cat);
            $catRated  = $catSkills->filter(fn($s) => isset($scores[$s->id]) && $scores[$s->id] > 0);
            $avg       = $catRated->count() > 0
                ? $catRated->sum(fn($s) => $scores[$s->id]) / $catRated->count()
                : 0;

            return [
                $cat => [
                    'average' => round($avg, 2),
                    'count'   => $catRated->count(),
                ],
            ];
        });

        return [
            'summary' => [
                'average_score'   => $assessment->average_score ?? 0,
                'skills_rated'    => $ratedSkills->count(),
                'total_skills'    => $skills->count(),
                'gaps_found'      => $gapSkills->count(),
                'strengths_found' => $strongSkills->count(),
            ],
            'category_breakdown' => $categoryAverages->toArray(),
            'gaps'      => $gapSkills->map(fn($s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'description'    => $s->description,
                'category'       => $s->category,
                'score'          => $scores[$s->id] ?? 0,
                'recommendation' => $s->recommendation ? [
                    'actions'         => $s->recommendation->actions,
                    'resources'       => $s->recommendation->resources,
                    'manager_support' => $s->recommendation->manager_support,
                    'timeline'        => $s->recommendation->timeline,
                ] : null,
            ])->values()->toArray(),
            'strengths' => $strongSkills->map(fn($s) => [
                'id'    => $s->id,
                'name'  => $s->name,
                'score' => $scores[$s->id] ?? 0,
            ])->values()->toArray(),
        ];
    }

    /**
     * Create development plans from gap analysis
     */
    public function createDevelopmentPlans(SkillAssessment $assessment): Collection
    {
        $scores = $assessment->scores ?? [];

        if (empty($scores)) {
            return collect();
        }

        $gapSkillIds = array_filter(
            array_keys($scores),
            fn($skillId) => (int) $scores[$skillId] <= 3
        );

        if (empty($gapSkillIds)) {
            return collect();
        }

        $gaps       = Skill::whereIn('id', $gapSkillIds)->get();
        $plans      = collect();
        $now        = Carbon::now();
        $targetDate = $now->copy()->addMonths(3);

        foreach ($gaps as $skill) {
            $plan = DevelopmentPlan::create([
                'employee_id'         => $assessment->employee_id,
                'skill_assessment_id' => $assessment->id,
                'skill_id'            => $skill->id,
                'status'              => 'planned',
                'start_date'          => $now,
                'target_date'         => $targetDate,
                'actions'             => $skill->recommendation?->actions,
                'resources'           => $skill->recommendation?->resources,
            ]);

            $plans->push($plan);
        }

        return $plans;
    }

    /**
     * Get summary statistics by department
     */
    public function getDepartmentStats(?int $departmentId = null): array
    {
        $query = SkillAssessment::where('status', SkillAssessment::STATUS_DONE);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $assessments = $query->get();

        return [
            'total_assessments'   => $assessments->count(),
            'average_score'       => round($assessments->avg('average_score') ?? 0, 2),
            'employees_with_gaps' => $assessments->filter(fn($a) => $a->gap_count > 0)->count(),
            'top_gap_skills'      => $this->getTopGapSkills($assessments),
        ];
    }

    /**
     * Get most common skill gaps across employees
     */
    private function getTopGapSkills(Collection $assessments): array
    {
        $gapCounts = [];

        foreach ($assessments as $assessment) {
            $scores = $assessment->scores ?? [];
            foreach ($scores as $skillId => $score) {
                if ((int) $score <= 3) {
                    $gapCounts[$skillId] = ($gapCounts[$skillId] ?? 0) + 1;
                }
            }
        }

        if (empty($gapCounts)) {
            return [];
        }

        arsort($gapCounts);
        $topSkillIds = array_slice(array_keys($gapCounts), 0, 10);
        $topSkills   = Skill::whereIn('id', $topSkillIds)->get();

        return $topSkills->map(fn($s) => [
            'skill_id'  => $s->id,
            'name'      => $s->name,
            'gap_count' => $gapCounts[$s->id],
        ])->toArray();
    }
}