<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Division;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['division_code' => 'AAO','code' => 'ACCT', 'name' => 'ACCOUNTING','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'ADM',  'name' => 'ADMITTING/INFORMATION','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'ACL',  'name' => 'ANATOMIC AND CLINICAL LABORATORY','type' => 'DEPARTMENT'],
            ['division_code' => 'AAO','code' => 'BILL', 'name' => 'BILLING AND CLAIMS','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'BB',   'name' => 'BLOOD BANK','type' => 'DEPARTMENT'],
            ['division_code' => 'AAO','code' => 'BUD',  'name' => 'BUDGET','type' => 'DEPARTMENT'],
            ['division_code' => 'AAO','code' => 'CASH', 'name' => 'CASH OPERATIONS','type' => 'DEPARTMENT'],
            ['division_code' => 'CN','code' => 'CSS',  'name' => 'CENTRAL SUPPLY AND STERILIZATION','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'CLIN', 'name' => 'CLINICAL DEPARTMENTS','type' => 'DEPARTMENT'],
            ['division_code' => 'CN','code' => 'CNU',  'name' => 'CLINICAL NURSING UNITS','type' => 'DEPARTMENT'],
            ['division_code' => 'CN','code' => 'DR',   'name' => 'DELIVERY ROOM','type' => 'DEPARTMENT'],      
            ['division_code' => 'MED','code' => 'DS',   'name' => 'DENTAL SERVICES','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'PATH', 'name' => 'DEPARTMENT OF PATHOLOGY','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'RAD',  'name' => 'DEPARTMENT OF RADIOLOGY','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'EMD',  'name' => 'EMERGENCY MEDICINE DEPARTMENT','type' => 'DEPARTMENT'],
            ['division_code' => 'AAO','code' => 'ENG',  'name' => 'ENGINEERING AND FACILITIES MANAGEMENT','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'HIM',  'name' => 'HEALTH INFORMATION','type' => 'DEPARTMENT'],
            ['division_code' => 'AAO','code' => 'HK',   'name' => 'HOUSEKEEPING/LAUNDRY','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'HMB',  'name' => 'HUMAN MILK BANK','type' => 'DEPARTMENT'],
            ['division_code' => 'AAO','code' => 'HR',   'name' => 'HUMAN RESOURCE MANAGEMENT','type' => 'DEPARTMENT'],
            ['division_code' => 'CN','code' => 'ICU',  'name' => 'INTENSIVE CARE UNIT','type' => 'DEPARTMENT'],
            ['division_code' => 'AAO','code' => 'MM',   'name' => 'MATERIALS MANAGEMENT','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'MED',  'name' => 'MEDICAL SERVICE','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'MSW',  'name' => 'MEDICAL SOCIAL WORK','type' => 'DEPARTMENT'],
            ['division_code' => 'CN','code' => 'NICU', 'name' => 'NEONATAL INTENSIVE CARE UNIT (NICU)','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'ND',   'name' => 'NUTRITION AND DIETETICS','type' => 'DEPARTMENT'],
            ['division_code' => 'AAO','code' => 'AAO',  'name' => 'OFFICE OF THE ADMINISTRATIVE OFFICER','type' => 'DEPARTMENT'],
            ['division_code' => 'CN','code' => 'CN',   'name' => 'OFFICE OF THE CHIEF NURSE','type' => 'DEPARTMENT'],
            ['division_code' => 'CH','code' => 'CH',   'name' => 'OFFICE OF THE CHIEF OF HOSPITAL','type' => 'DEPARTMENT'],
            ['division_code' => 'CN','code' => 'OR',   'name' => 'OPERATING ROOM','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'OPU',  'name' => 'OUT-PATIENT UNIT','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'PH',   'name' => 'PHARMACY','type' => 'DEPARTMENT'],
            ['division_code' => 'AAO','code' => 'PROC', 'name' => 'PROCUREMENT','type' => 'DEPARTMENT'],
            ['division_code' => 'CN','code' => 'PRU',  'name' => 'PULMONARY/RESPIRATORY UNIT','type' => 'DEPARTMENT'],
            ['division_code' => 'MED','code' => 'SCA',  'name' => 'SPECIAL CARE AREAS','type' => 'DEPARTMENT'],
            ['division_code' => 'CN','code' => 'SCA-PACU', 'name' => 'SPECIAL CARE AREAS - POST ANESTHESIA CARE UNIT','type' => 'DEPARTMENT'],
        ];


         $now = now();

        $rows = [];
        foreach ($departments as $d) {
            $div = Division::where('code', $d['division_code'])->first();
            if (!$div) continue;

            $rows[] = [
                'division_id' => $div->id,
                'parent_id' => $d['parent_id'] ?? null, // later if you want nesting
                'type' => $d['type'] ?? 'DEPARTMENT',
                'code' => $d['code'],
                'name' => $d['name'],
                'description' => $d['description'] ?? null,
                'head_employee_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('departments')->upsert(
            $rows,
            ['division_id', 'code'],
            ['parent_id', 'type', 'name', 'description', 'head_employee_id', 'updated_at']
        );
    }
}