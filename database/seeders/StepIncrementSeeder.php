<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StepIncrement;
use App\Models\SalaryGrade;

class StepIncrementSeeder extends Seeder
{
    public function run(): void
    {

        $grades = SalaryGrade::all();

        foreach ($grades as $grade) {

            for ($step = 1; $step <= 8; $step++) {

                StepIncrement::create([
                    'salary_grade_id' => $grade->id,
                    'step' => $step,
                    'monthly_salary' => null,
                    'annual_salary' => null
                ]);

            }

        }

    }
}