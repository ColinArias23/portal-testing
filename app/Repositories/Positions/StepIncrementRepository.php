<?php

namespace App\Repositories\Positions;

use App\Models\StepIncrement;
use Illuminate\Support\Collection;

class StepIncrementRepository
{
    public function listing(?int $salaryGradeId): Collection
    {
        $query = StepIncrement::query()->with('salaryGrade');

        if ($salaryGradeId !== null) {
            $query->where('salary_grade_id', $salaryGradeId);
        }

        return $query->orderBy('salary_grade_id')
            ->orderBy('step')
            ->get(['id', 'salary_grade_id', 'step', 'monthly_salary', 'annual_salary']);
    }

    public function existsForGradeAndStep(int $salaryGradeId, int $step, ?int $excludeId = null): bool
    {
        return StepIncrement::where('salary_grade_id', $salaryGradeId)
            ->where('step', $step)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    public function create(array $data): StepIncrement
    {
        return StepIncrement::create($data)->load('salaryGrade');
    }

    public function loadSalaryGrade(StepIncrement $step): StepIncrement
    {
        return $step->load('salaryGrade');
    }

    public function update(StepIncrement $step, array $data): StepIncrement
    {
        $step->update($data);

        return $step->fresh('salaryGrade');
    }

    public function delete(StepIncrement $step): void
    {
        $step->delete();
    }
}
