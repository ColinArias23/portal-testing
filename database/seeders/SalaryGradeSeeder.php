<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalaryGrade;

class SalaryGradeSeeder extends Seeder
{
    public function run(): void
    {
        for ($sg = 1; $sg <= 33; $sg++) {

            SalaryGrade::updateOrCreate(
                ['salary_grade' => $sg],
                [
                    'monthly_salary' => null,
                    'annual_salary' => null
                ]
            );

        }
    }
}