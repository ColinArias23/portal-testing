<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrgUnit;

class OrgUnitSeeder extends Seeder
{
  public function run(): void
  {
    /**
     * These came from your old $departments array.
     * Now we store them in org_units table.
     *
     * - code: unique code (MED, AAO, CN, CH)
     * - name: department/office name
     * - type: Department / Office / Unit / Section / Committee
     * - parent_id: nullable (set later if you have hierarchy)
     * - division_id: nullable (set later if you already have divisions)
     */
    $orgUnits = [
      [
        'code' => 'MED',
        'name' => 'MEDICAL SERVICE',
        'type' => 'Department',
        'division_id' => null,
        'parent_id' => null,
        'description' => 'Handles medical services for patients.',
      ],
      [
        'code' => 'AAO',
        'name' => 'OFFICE OF THE ADMINISTRATIVE OFFICER',
        'type' => 'Office',
        'division_id' => null,
        'parent_id' => null,
        'description' => 'Manages administrative operations and support services.',
      ],
      [
        'code' => 'CN',
        'name' => 'OFFICE OF THE CHIEF NURSE',
        'type' => 'Office',
        'division_id' => null,
        'parent_id' => null,
        'description' => 'Oversees nursing staff and patient care standards.',
      ],
      [
        'code' => 'CH',
        'name' => 'OFFICE OF THE CHIEF OF HOSPITAL',
        'type' => 'Office',
        'division_id' => null,
        'parent_id' => null,
        'description' => 'Directs hospital management and operations.',
      ],
    ];

    foreach ($orgUnits as $row) {
      OrgUnit::updateOrCreate(
        ['code' => $row['code']],
        [
          'name'        => $row['name'],
          'type'        => $row['type'] ?? 'Department',
          'division_id' => $row['division_id'] ?? null,
          'parent_id'   => $row['parent_id'] ?? null,
          'description' => $row['description'] ?? null,
        ]
      );
    }
  }
}
