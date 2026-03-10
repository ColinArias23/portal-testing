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
            [
                'employee_number'   => 'RMBGH-0132527',
                'plantilla_item_no' => '2',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
            ],
            [
                'employee_number'   => 'RMBGH-0111538',
                'plantilla_item_no' => '7',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
            ],
            [
                'employee_number'   => 'RMBGH-0133434',
                'plantilla_item_no' => '37-3',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
            ],
            [
                'employee_number'   => 'RMBGH-0133396',
                'plantilla_item_no' => '36-3',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
            ],
            [
                'employee_number'   => 'RMBGH-0133566',
                'plantilla_item_no' => '36-7',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
            ],
            [
                'employee_number'   => 'RMBGH-0111724',
                'plantilla_item_no' => '20-1',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
            ],
            [
                'employee_number'   => 'RMBGH-0122688',
                'plantilla_item_no' => '130-1',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
            ],
            [
                'employee_number'   => 'RMBGH-0111104',
                'plantilla_item_no' => '66',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
            ],
            [
                'employee_number'   => 'RMBGH-0111708',
                'plantilla_item_no' => '17',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
            ],
            [
                'employee_number'   => 'RMBGH-0137022',
                'plantilla_item_no' => '12-1',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
            ],
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