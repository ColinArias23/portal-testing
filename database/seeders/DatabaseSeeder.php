<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            DivisionSeeder::class,
            PlantillaItemsSeeder::class,

            EmployeeSeeder::class,
            EmployeeHierarchySeeder::class,

            RbacSeeder::class,
            UserSeeder::class,
        ]);
    }
}
