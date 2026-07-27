<?php

namespace App\Services\Positions;

use App\Models\StepIncrement;
use App\Repositories\Positions\StepIncrementRepository;
use Illuminate\Support\Collection;

class StepIncrementService
{
    public function __construct(
        private readonly StepIncrementRepository $steps,
    ) {
    }

    public function index(?int $salaryGradeId): Collection
    {
        return $this->steps->listing($salaryGradeId);
    }

    public function store(array $data): array
    {
        if ($this->steps->existsForGradeAndStep($data['salary_grade_id'], $data['step'])) {
            return [
                'status' => 422,
                'body' => ['message' => 'Step already exists for this salary grade.'],
            ];
        }

        return ['status' => 201, 'body' => $this->steps->create($data)];
    }

    public function show(StepIncrement $step): StepIncrement
    {
        return $this->steps->loadSalaryGrade($step);
    }

    public function update(StepIncrement $step, array $data): array
    {
        $salaryGradeId = $data['salary_grade_id'] ?? $step->salary_grade_id;
        $stepNumber    = $data['step']            ?? $step->step;

        if ($this->steps->existsForGradeAndStep($salaryGradeId, $stepNumber, $step->id)) {
            return [
                'status' => 422,
                'body' => ['message' => 'Step already exists for this salary grade.'],
            ];
        }

        return ['status' => 200, 'body' => $this->steps->update($step, $data)];
    }

    public function destroy(StepIncrement $step): void
    {
        $this->steps->delete($step);
    }
}
