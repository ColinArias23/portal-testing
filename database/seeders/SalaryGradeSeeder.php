<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalaryGrade;

class SalaryGradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            1 => 11068,
            2 => 11761,
            3 => 12466,
            4 => 13214,
            5 => 14007,
            6 => 14847,
            7 => 15738,
            8 => 16758,
            9 => 17975,
            10 => 19233,
            11 => 20754,
            12 => 22938,
            13 => 25232,
            14 => 27755,
            15 => 30531,
            16 => 33584,
            17 => 36942,
            18 => 40637,
            19 => 45269,
            20 => 51155,
            21 => 57805,
            22 => 65319,
            23 => 73811,
            24 => 83406,
            25 => 95083,
            26 => 107444,
            27 => 121411,
            28 => 137195,
            29 => 155030,
            30 => 175184,
            31 => 257809,
            32 => 307365,
            33 => 388096,
        ];

        foreach ($grades as $sg => $step1Salary) {

            SalaryGrade::updateOrCreate(
                ['salary_grade' => $sg],
                [
                    'monthly_salary' => $step1Salary,
                    'annual_salary' => $step1Salary * 12,
                ]
            );
        }
    }
}