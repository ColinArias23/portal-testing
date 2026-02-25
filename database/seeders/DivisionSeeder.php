<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            [
                'code' => 'MED',  
                'name' => 'MEDICAL SERVICE', 
                'description' => 'Handles medical services for patients.',
                'head_employee_id' => 5,
                'parent_id' => 1
            ],
            [
                'code' => 'AAO',  
                'name' => 'OFFICE OF THE ADMINISTRATIVE OFFICER', 
                'description' => 'Manages administrative operations and support services.',
                'head_employee_id' => 6,
                'parent_id' => 1
            ],
            [
                'code' => 'CN',   
                'name' => 'OFFICE OF THE CHIEF NURSE', 
                'description' => 'Oversees nursing staff and patient care standards.',
                'head_employee_id' => 8,
                'parent_id' => 1
            ],
            [
                'code' => 'CH',   
                'name' => 'OFFICE OF THE CHIEF OF HOSPITAL', 
                'description' => 'Directs hospital management and operations.',
                'head_employee_id' => 4,
                'parent_id' => 1
            ],
        ];

        $now = now();

        $rows = array_map(fn ($d) => [
            'code' => $d['code'],
            'name' => $d['name'],
            'description' => $d['description'] ?? null,
            'head_employee_id' => $d['head_employee_id'],
            'parent_id' => $d['parent_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $divisions);

        DB::table('divisions')->upsert(
            $rows,
            ['code'],
            ['name', 'description', 'parent_id', 'updated_at']
        );
    }
}