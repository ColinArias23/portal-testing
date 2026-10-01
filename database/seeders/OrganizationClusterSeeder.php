<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationClusterSeeder extends Seeder
{
    public function run(): void
    {
        $division = Division::where('code', 'HOPSS')->first();

        if (! $division) {
            $this->command?->error("OrganizationClusterSeeder: Division 'HOPSS' not found. Aborting.");
            return;
        }

        $now = now();

        $clusters = [
            [
                'code' => 'HIMC',
                'name' => 'HEALTH INFORMATION MANAGEMENT CLUSTER',
                'head_first_name' => 'Ruel',
                'head_last_name' => 'Subaran',
            ],
            [
                'code' => 'ASC',
                'name' => 'ADMINISTRATIVE SERVICES CLUSTER',
                'head_first_name' => 'Cristela',
                'head_last_name' => 'Veñegas',
            ],
        ];

        foreach ($clusters as $cluster) {
            $headId = Employee::whereRaw('UPPER(first_name) = ?', [mb_strtoupper($cluster['head_first_name'])])
                ->whereRaw('UPPER(last_name) = ?', [mb_strtoupper($cluster['head_last_name'])])
                ->value('id');

            if (! $headId) {
                $this->command?->warn(
                    "OrganizationClusterSeeder: could not find employee '{$cluster['head_first_name']} {$cluster['head_last_name']}' "
                    . "for cluster '{$cluster['code']}'. Creating the cluster with no head \u2014 please assign manually."
                );
            }

            DB::table('departments')->upsert([
                [
                    'division_id' => $division->id,
                    'type' => 'CLUSTER',
                    'code' => $cluster['code'],
                    'name' => $cluster['name'],
                    'description' => null,
                    'head_employee_id' => $headId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ], ['division_id', 'code'], ['type', 'name', 'head_employee_id', 'updated_at']);
        }

        Department::where('division_id', $division->id)
            ->where('code', 'PCS')
            ->update(['type' => 'CLUSTER', 'updated_at' => $now]);

        $reparenting = [
            'HIMC' => ['ADM', 'MEDREC', 'IT'],
            'ASC' => ['HR', 'DPO', 'CRM'],
            'PCS' => ['MSS', 'DIET'],
        ];

        foreach ($reparenting as $parentCode => $childCodes) {
            $parentId = DB::table('departments')
                ->where('division_id', $division->id)
                ->where('code', $parentCode)
                ->value('id');

            if (! $parentId) {
                $this->command?->warn("OrganizationClusterSeeder: parent code '{$parentCode}' not found. Skipping its children.");
                continue;
            }

            $updated = DB::table('departments')
                ->where('division_id', $division->id)
                ->whereIn('code', $childCodes)
                ->update(['parent_id' => $parentId, 'updated_at' => $now]);

            $this->command?->info("OrganizationClusterSeeder: re-parented {$updated} department(s) under '{$parentCode}'.");
        }
    }
}