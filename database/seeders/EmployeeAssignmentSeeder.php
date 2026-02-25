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
            [
                'employee_number'   => 'RMBGH-0132527',
                'department_id'     => 29,
                'division_id'       => 4,
                'plantilla_item_no' => '2',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
                'status'            => 'ACTIVE',
                'remarks'           => 'Medical Chief',
            ],

            // Maria (Primary)
            [
                'employee_number'   => 'RMBGH-0111538',
                'department_id'     => 29,
                'division_id'       => 4,
                'plantilla_item_no' => '8-1',
                'is_primary'        => true,
                'start_date'        => '2024-01-01',
                'end_date'          => null,
                'status'            => 'ACTIVE',
                'remarks'           => 'Primary Division',
            ],

            // Maria (Second Division)
            [
                'employee_number'   => 'RMBGH-0111538',
                'department_id'     => 29,
                'division_id'       => 1,
                'plantilla_item_no' => '9',
                'is_primary'        => false,
                'start_date'        => '2024-02-01',
                'end_date'          => null,
                'status'            => 'ACTIVE',
                'remarks'           => 'Second Division',
            ],

        ];

        foreach ($rows as $r) {

            $employee = Employee::where('employee_number', $r['employee_number'])->first();
            if (!$employee) continue; // skip if not found

            $plantillaId = null;
            if (!empty($r['plantilla_item_no'])) {
                $plantillaId = PlantillaItem::where('item_number', $r['plantilla_item_no'])->value('id');
            }

            EmployeeAssignment::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'division_id' => $r['division_id'],
                    'status'      => $r['status'],
                ],
                [
                    'department_id'     => $r['department_id'],
                    'plantilla_item_id' => $plantillaId,
                    'is_primary'        => $r['is_primary'],
                    'start_date'        => $r['start_date'],
                    'end_date'          => $r['end_date'],
                    'remarks'           => $r['remarks'],
                ]
            );
        }
    }
}