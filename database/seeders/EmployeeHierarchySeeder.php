<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\EmployeeHierarchy;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class EmployeeHierarchySeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Guard: if migration not yet ran / table missing, skip gracefully
        if (!Schema::hasTable('employee_hierarchies')) {
            $this->command?->warn("Skipping EmployeeHierarchySeeder: table employee_hierarchies does not exist.");
            return;
        }

        if (!Schema::hasTable('employees')) {
            $this->command?->warn("Skipping EmployeeHierarchySeeder: table employees does not exist.");
            return;
        }

        // ✅ Reset safely (FK safe)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('employee_hierarchies')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // helper: get employee id by employee_number
        $empId = function (string $employeeNumber): int {
            $emp = Employee::query()
                ->where('employee_number', $employeeNumber)
                ->first();

            if (!$emp) {
                throw new \RuntimeException("Employee not found: {$employeeNumber}");
            }

            return (int) $emp->id;
        };

        // helper: link parent -> child (with optional division)
        $link = function (string $parentEmpNo, string $childEmpNo, ?int $divisionId = null) use ($empId) {
            EmployeeHierarchy::updateOrCreate(
                [
                    'parent_id' => $empId($parentEmpNo),
                    'child_id'  => $empId($childEmpNo),
                    // NOTE: if you used unique(parent_id,child_id,division_id) in migration,
                    // you can include division_id in the unique keys instead. Example:
                    // 'division_id' => $divisionId,
                ],
                [
                    'division_id' => $divisionId,
                ]
            );
        };

        /**
         * =========================================================
         * ROOT (Medical Center Chief)
         * =========================================================
         */
        $chief = 'RMBGH-0132527';

        /**
         * =========================================================
         * Committee (children of Chief)
         * =========================================================
         */
        $link($chief, 'RMBGH-0111538');
        $link($chief, 'RMBGH-0133434');
        $link($chief, 'VACANT-0000001'); // vacant slot

        /**
         * =========================================================
         * Hospital Director (child of Chief)
         * =========================================================
         */
        $hospitalDirector = 'RMBGH-0133396';
        $link($chief, $hospitalDirector);

        /**
         * =========================================================
         * Directors under Hospital Director
         * =========================================================
         */
        $medicalDirector  = 'RMBGH-0133566';
        $adminDirector    = 'RMBGH-0000001';
        $financeDirector  = 'RMBGH-0000002';
        $nursingDirector  = 'RMBGH-0000003';

        $link($hospitalDirector, $medicalDirector);
        $link($hospitalDirector, $adminDirector);
        $link($hospitalDirector, $financeDirector);
        $link($hospitalDirector, $nursingDirector);

        /**
         * =========================================================
         * Medical Director -> Medical Dept Heads
         * =========================================================
         */
        $link($medicalDirector, 'RMBGH-0111058');  // medical dept head
        $link($medicalDirector, 'RMBGH-0142875');  // medical dept head
        $link($medicalDirector, 'RMBGH-0111589');  // medical dept head

        // Add extra medical related people you seeded (optional mapping)
        $link($medicalDirector, 'RMBGH-0093351');
        $link($medicalDirector, 'RMBGH-0142867'); // (note: you used this also under finance earlier in list; if duplicate emp_no, latest record wins)
        $link($medicalDirector, 'RMBGH-0000005'); // (same note)
        $link($medicalDirector, 'CS-0027341');
        $link($medicalDirector, 'CS-0042725');
        $link($medicalDirector, 'CS-0026258');
        $link($medicalDirector, 'RMBGH-0111104'); // medical director also head of another dept (based on your comments)
        $link($medicalDirector, 'RMBGH-0129976');
        $link($medicalDirector, 'RMBGH-0111023');
        $link($medicalDirector, 'VACANT-0000002');
        $link($medicalDirector, 'VACANT-0000003');
        $link($medicalDirector, 'RMBGH-0129941');

        /**
         * =========================================================
         * Admin Director -> Admin Dept Heads
         * =========================================================
         */
        $link($adminDirector, 'RMBGH-0111155');
        $link($adminDirector, 'RMBGH-0000004');
        $link($adminDirector, 'RMBGH-0121991');
        $link($adminDirector, 'RMBGH-0129968');

        // Admin branch deeper (based on your later seeded groups)
        $link('RMBGH-0111155', 'RMBGH-0142905');
        $link('RMBGH-0111155', 'CT-0025794');
        $link('RMBGH-0111155', 'RMBGH-0111082');
        $link('RMBGH-0111155', 'CT-0008704');

        $link('RMBGH-0000004', 'RMBGH-0111295');
        $link('RMBGH-0000004', 'RMBGH-0143065');
        $link('RMBGH-0000004', 'RMBGH-0142816');

        $link('RMBGH-0121991', 'RMBGH-0122122');
        $link('RMBGH-0121991', 'VACANT-0000004');
        $link('RMBGH-0121991', 'RMBGH-0075973');

        $link('RMBGH-0129968', 'RMBGH-0111295'); // MA. Cristina... (you reused emp no RMBGH-0111295 earlier too)
        $link('RMBGH-0129968', 'RMBGH-0143065'); // Teresita... (you reused emp no RMBGH-0143065 earlier too)

        /**
         * =========================================================
         * Finance Director -> Finance heads/staff
         * =========================================================
         */
        $link($financeDirector, 'RMBGH-0142883');
        $link($financeDirector, 'RMBGH-0111597');
        $link($financeDirector, 'RMBGH-0142867');
        $link($financeDirector, 'RMBGH-0000005');
        $link($financeDirector, 'RMBGH-0111457');

        /**
         * =========================================================
         * Nursing Director -> Nursing Heads
         * =========================================================
         */
        $link($nursingDirector, 'RMBGH-0111503'); // MA. Vicarl
        $link($nursingDirector, 'RMBGH-0122734'); // Lady Jane

        // Nursing: direct reports / vacancies under Lady Jane
        $link('RMBGH-0122734', 'VACANT-0000005');
        $link('RMBGH-0122734', 'VACANT-0000006');

        // Nursing: staff under MA. Vicarl
        $link('RMBGH-0111503', 'CT-0031791');
        $link('RMBGH-0111503', 'RMBGH-0122769');
        $link('RMBGH-0111503', 'RMBGH-0122742');
        $link('RMBGH-0111503', 'RMBGH-0111922');
        $link('RMBGH-0111503', 'RMBGH-0111422');
        $link('RMBGH-0111503', 'RMBGH-0083984');
        $link('RMBGH-0111503', 'RMBGH-0111287');

        $this->command?->info("EmployeeHierarchySeeder: hierarchy links created.");
    }
}