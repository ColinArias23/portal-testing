<?php

namespace Database\Seeders;

use App\Models\PschedDivision;
use App\Models\PschedLine;
use App\Models\PschedSlot;
use App\Models\PschedUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PersonnelScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (PschedDivision::query()->exists()) {
            $this->command?->warn('Personnel schedule already loaded. Skipped so existing edits and assignments are kept.');

            return;
        }

        $schedule = require database_path('seeders/data/personnel_schedule.php');
        $now = now();

        DB::transaction(function () use ($schedule, $now) {
            $unitOrder = 0;
            $lineOrder = 0;

            foreach ($schedule as $divisionIndex => $divisionData) {
                $division = PschedDivision::create([
                    'name' => $divisionData['name'],
                    'type' => $divisionData['type'],
                    'status' => 'ACTIVE',
                    'sort_order' => $divisionIndex + 1,
                ]);

                foreach ($divisionData['units'] as $unitData) {
                    $unit = null;

                    if ($unitData['name'] !== null) {
                        $unit = PschedUnit::create([
                            'division_id' => $division->id,
                            'name' => $unitData['name'],
                            'type' => $unitData['type'],
                            'status' => 'ACTIVE',
                            'sort_order' => ++$unitOrder,
                        ]);
                    }

                    foreach ($unitData['positions'] as $position) {
                        $line = PschedLine::create([
                            'division_id' => $division->id,
                            'unit_id' => $unit?->id,
                            'title' => $position['title'],
                            'salary_grade' => $position['sg'],
                            'sort_order' => ++$lineOrder,
                        ]);

                        PschedSlot::insert(array_map(
                            fn ($itemNumber, $index) => [
                                'line_id' => $line->id,
                                'item_number' => $itemNumber,
                                'sort_order' => $index + 1,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ],
                            $position['items'],
                            array_keys($position['items']),
                        ));
                    }
                }
            }
        });
    }
}