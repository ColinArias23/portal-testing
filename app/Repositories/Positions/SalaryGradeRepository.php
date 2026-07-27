<?php

namespace App\Repositories\Positions;

use App\Models\SalaryGrade;
use Illuminate\Support\Collection;

class SalaryGradeRepository
{
    public function allForListing(): Collection
    {
        return SalaryGrade::orderBy('salary_grade')
            ->get(['id', 'salary_grade', 'monthly_salary', 'annual_salary']);
    }

    public function options(): Collection
    {
        return SalaryGrade::orderBy('salary_grade')->get(['id', 'salary_grade']);
    }

    public function create(array $data): SalaryGrade
    {
        return SalaryGrade::create($data);
    }

    public function loadStepIncrements(SalaryGrade $grade): SalaryGrade
    {
        return $grade->load('stepIncrements');
    }

    public function update(SalaryGrade $grade, array $data): SalaryGrade
    {
        $grade->update($data);

        return $grade->fresh();
    }

    public function isInUse(SalaryGrade $grade): bool
    {
        return $grade->plantillaItems()->exists();
    }

    public function delete(SalaryGrade $grade): void
    {
        $grade->delete();
    }
}
