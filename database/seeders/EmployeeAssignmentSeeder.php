<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\PlantillaItem;

class EmployeeAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [

            // Dave
            // [
            //     'employee_number'   => 'RMBGH-0132527',
            //     'plantilla_item_no' => '2',
            //     'is_primary'        => true,
            //     'start_date'        => '2024-01-01',
            //     'end_date'          => null,
            // ],

            // // Maria (Primary)
            // [
            //     'employee_number'   => 'RMBGH-0111538',
            //     'plantilla_item_no' => '8-1',
            //     'is_primary'        => true,
            //     'start_date'        => '2024-01-01',
            //     'end_date'          => null,
            // ],

            // // Maria (Second Assignment)
            // [
            //     'employee_number'   => 'RMBGH-0111538',
            //     'plantilla_item_no' => '9',
            //     'is_primary'        => false,
            //     'start_date'        => '2024-02-01',
            //     'end_date'          => null,
            // ],
        ];

        foreach ($rows as $r) {

            $employee = Employee::where('employee_number', $r['employee_number'])->first();
            if (!$employee) continue;

            $plantilla = PlantillaItem::where('item_number', $r['plantilla_item_no'])->first();
            if (!$plantilla) continue;

            EmployeeAssignment::updateOrCreate(
                [
                    'employee_id'        => $employee->id,
                    'plantilla_item_id'  => $plantilla->id,
                    'start_date'         => $r['start_date'],
                ],
                [
                    'is_primary' => $r['is_primary'],
                    'end_date'   => $r['end_date'],
                ]
            );
        }
    }
}