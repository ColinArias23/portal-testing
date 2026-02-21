<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Division;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'department_code' => 'MED',
                'code' => 'MED',
                'name' => 'MEDICAL SERVICE',
                'description' => 'Handles medical services for patients.',
            ],
            [
                'department_code' => 'AAO',
                'code' => 'AAO',
                'name' => 'OFFICE OF THE ADMINISTRATIVE OFFICER',
                'description' => 'Manages administrative operations and support services.',
            ],
            [
                'department_code' => 'CN',
                'code' => 'CN',
                'name' => 'OFFICE OF THE CHIEF NURSE',
                'description' => 'Oversees nursing staff and patient care standards.',
            ],
            [
                'department_code' => 'CH',
                'code' => 'CH',
                'name' => 'OFFICE OF THE CHIEF OF HOSPITAL',
                'description' => 'Directs hospital management and operations.',
            ],
        ];

        foreach ($rows as $d) {
            $dept = Department::where('code', $d['department_code'])->first();

            // skip if department not found
            if (!$dept) continue;

            Division::updateOrCreate(
                [
                    'department_id' => $dept->id,
                    'code' => $d['code'],
                ],
                [
                    'name' => $d['name'],
                    'description' => $d['description'] ?? null,
                ]
            );
        }
    }
}
