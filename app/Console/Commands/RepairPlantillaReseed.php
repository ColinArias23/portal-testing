<?php

namespace App\Console\Commands;

use App\Models\SalaryGrade;
use App\Models\StepIncrement;
use Database\Seeders\PlantillaPositionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;

class RepairPlantillaReseed extends Command
{
    private const SEEDED_ROWS = 428;
    private const LAST_LEGIT_ID = 435;
    private const RESEED_ROWS = 265;
    private const FIXED_CM3_ID = 429;
    private const FIXED_CM3_TITLE = 'COMPUTER MAINTENANCE III';

    protected $signature = 'plantilla:repair-reseed {--apply : Write the changes. Without this flag nothing is modified}';

    protected $description = 'Remove the rows created by the 2026-10-12 re-seed and restore the rows it overwrote.';

    public function handle(): int
    {
        $reseededIds = DB::table('plantilla_positions')
            ->where('id', '>', self::LAST_LEGIT_ID)
            ->pluck('id');

        if ($reseededIds->count() !== self::RESEED_ROWS) {
            $this->error("Expected " . self::RESEED_ROWS . " rows above id " . self::LAST_LEGIT_ID . ", found {$reseededIds->count()}. Aborting.");

            return self::FAILURE;
        }

        if ($referenced = $this->referencedTables($reseededIds->all())) {
            $this->error('Rows that would be deleted are still referenced by: ' . implode(', ', $referenced) . '. Aborting.');

            return self::FAILURE;
        }

        $current = DB::table('plantilla_positions')
            ->where('id', '<=', self::LAST_LEGIT_ID)
            ->get()
            ->keyBy('id');

        if ($current->count() !== self::LAST_LEGIT_ID) {
            $this->error('Ids 1-' . self::LAST_LEGIT_ID . ' are not all present. Aborting.');

            return self::FAILURE;
        }

        $final = $this->buildFinalState($current);

        if ($final === null) {
            return self::FAILURE;
        }

        $changed = $this->changedRows($current, $final);
        $nameChanges = $changed->filter(fn ($row) => $row['name_changed']);

        $this->info("Rows to delete: {$reseededIds->count()}");
        $this->info("Rows to restore: {$changed->count()} (slot name changes: {$nameChanges->count()})");

        $this->table(
            ['id', 'slot name', 'title now', 'title restored'],
            $changed->take(20)->map(fn ($row) => [
                $row['id'],
                $row['old_name'] . ($row['name_changed'] ? " -> {$row['new_name']}" : ''),
                mb_strimwidth((string) $row['old_title'], 0, 40, '...'),
                mb_strimwidth((string) $row['new_title'], 0, 40, '...'),
            ])->all()
        );

        if ($nameChanges->isNotEmpty()) {
            $this->error('Slot names were expected to stay the same. Refusing to continue, send me this output.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Back up the database, then run again with --apply.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($reseededIds, $final) {
            DB::table('plantilla_positions')->whereIn('id', $reseededIds->all())->delete();

            DB::table('plantilla_positions')
                ->where('id', '<=', self::LAST_LEGIT_ID)
                ->update(['position_slot_name' => DB::raw("CONCAT('TMP-', id)")]);

            foreach ($final as $id => $values) {
                DB::table('plantilla_positions')
                    ->where('id', $id)
                    ->update([...$values, 'updated_at' => now()]);
            }
        });

        $this->info('Repair applied.');

        return self::SUCCESS;
    }

    private function referencedTables(array $ids): array
    {
        $checks = [
            'employee_assignments'         => 'plantilla_position_id',
            'plantilla_posting_slots'      => 'plantilla_position_id',
            'plantilla_position_histories' => 'plantilla_position_id',
        ];

        $found = [];

        foreach ($checks as $table => $column) {
            if (DB::table($table)->whereIn($column, $ids)->exists()) {
                $found[] = "{$table}.{$column}";
            }
        }

        if (DB::table('plantilla_position_histories')->whereIn('related_position_id', $ids)->exists()) {
            $found[] = 'plantilla_position_histories.related_position_id';
        }

        return $found;
    }

    private function seededRows(): array
    {
        $property = new ReflectionProperty(PlantillaPositionSeeder::class, 'rows');

        return array_values($property->getValue(new PlantillaPositionSeeder()));
    }

    private function buildFinalState($current): ?array
    {
        $seeded = $this->seededRows();

        if (count($seeded) !== self::SEEDED_ROWS) {
            $this->error('Seeder has ' . count($seeded) . ' rows, expected ' . self::SEEDED_ROWS . '. Aborting.');

            return null;
        }

        $salaryGradeIds = SalaryGrade::pluck('id', 'salary_grade');
        $stepIds        = StepIncrement::where('step', 1)->pluck('id', 'salary_grade_id');
        $filledIds      = DB::table('employee_assignments')
            ->whereNull('end_date')
            ->whereNotNull('plantilla_position_id')
            ->pluck('plantilla_position_id')
            ->flip();

        $final = [];

        foreach ($seeded as $index => $row) {
            $id    = $index + 1;
            $parts = explode('-', $row['item_no']);
            $seedBase = (int) $parts[0];

            $base = $seedBase === 1 ? 2 : $seedBase + 2;

            $salaryGradeId = $salaryGradeIds[$row['sg']] ?? null;

            if ($salaryGradeId === null) {
                $this->error("Salary grade {$row['sg']} not found for seeder row {$row['item_no']}. Aborting.");

                return null;
            }

            $final[$id] = [
                'base_item_number' => (string) $base,
                'slot_number'      => (int) ($parts[1] ?? 1),
                'title'            => mb_strtoupper($row['title']),
                'position_title'   => mb_strtoupper($row['title']),
                'salary_grade_id'  => $salaryGradeId,
                'step_increment_id' => $stepIds[$salaryGradeId] ?? null,
                'status'           => isset($filledIds[$id]) ? 'FILLED' : 'UNFILLED',
            ];
        }

        for ($id = self::SEEDED_ROWS + 1; $id <= self::LAST_LEGIT_ID; $id++) {
            $row = $current[$id];

            $final[$id] = [
                'base_item_number'  => $row->base_item_number,
                'slot_number'       => $row->slot_number,
                'title'             => $id === self::FIXED_CM3_ID ? self::FIXED_CM3_TITLE : $row->title,
                'position_title'    => $id === self::FIXED_CM3_ID ? self::FIXED_CM3_TITLE : $row->position_title,
                'salary_grade_id'   => $row->salary_grade_id,
                'step_increment_id' => $row->step_increment_id,
                'status'            => isset($filledIds[$id]) ? 'FILLED' : 'UNFILLED',
            ];
        }

        $slotsPerBase = collect($final)->countBy('base_item_number');
        $usedNames    = [];

        foreach ($final as $id => $values) {
            $name = $slotsPerBase[$values['base_item_number']] === 1
                ? $values['base_item_number']
                : "{$values['base_item_number']}-{$values['slot_number']}";

            if (isset($usedNames[$name])) {
                $this->error("Slot name {$name} would be used by ids {$usedNames[$name]} and {$id}. Aborting.");

                return null;
            }

            $usedNames[$name] = $id;
            $final[$id]['position_slot_name'] = $name;
        }

        return $final;
    }

    private function changedRows($current, array $final)
    {
        $fields = ['base_item_number', 'slot_number', 'title', 'position_title', 'salary_grade_id', 'step_increment_id', 'status', 'position_slot_name'];

        return collect($final)
            ->map(function (array $values, int $id) use ($current, $fields) {
                $row = $current[$id];

                foreach ($fields as $field) {
                    if ((string) $row->{$field} !== (string) $values[$field]) {
                        return [
                            'id'           => $id,
                            'old_name'     => $row->position_slot_name,
                            'new_name'     => $values['position_slot_name'],
                            'name_changed' => $row->position_slot_name !== $values['position_slot_name'],
                            'old_title'    => $row->title,
                            'new_title'    => $values['title'],
                        ];
                    }
                }

                return null;
            })
            ->filter()
            ->values();
    }
}