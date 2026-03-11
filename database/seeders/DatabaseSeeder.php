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
            PlantillaItemsSeeder::class,
            DivisionSeeder::class,
            DepartmentSeeder::class,
            EmployeeSeeder::class,
            EmployeeHierarchySeeder::class,
            // RbacSeeder::class,
            UserSeeder::class,
            EmployeeAssignmentSeeder::class,
        ]);
    }
}
