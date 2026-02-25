<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DivisionSeeder::class,
            DepartmentSeeder::class,
            PlantillaItemsSeeder::class,

            EmployeeSeeder::class,
            // EmployeeHierarchySeeder::class,

            RbacSeeder::class,
            UserSeeder::class,
            EmployeeAssignmentSeeder::class,
        ]);
    }
}
