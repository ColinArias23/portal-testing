<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SalaryGradeSeeder::class,
            StepIncrementSeeder::class,
            PlantillaPositionSeeder::class,
            CosPositionSeeder::class,
            ConsultantPositionSeeder::class,
            DivisionSeeder::class,
            DepartmentSeeder::class,
            // EmployeeSeeder::class,
            SkillSeeder::class, 
            RbacSeeder::class,
            UserSeeder::class,
        ]);
    }
}
