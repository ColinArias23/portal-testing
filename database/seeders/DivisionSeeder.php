<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            ['code' => 'ACCT', 'name' => 'ACCOUNTING'],
            ['code' => 'ADM',  'name' => 'ADMITTING/INFORMATION'],
            ['code' => 'ACL',  'name' => 'ANATOMIC AND CLINICAL LABORATORY'],
            ['code' => 'BILL', 'name' => 'BILLING AND CLAIMS'],
            ['code' => 'BB',   'name' => 'BLOOD BANK'],
            ['code' => 'BUD',  'name' => 'BUDGET'],
            ['code' => 'CASH', 'name' => 'CASH OPERATIONS'],
            ['code' => 'CSS',  'name' => 'CENTRAL SUPPLY AND STERILIZATION'],
            ['code' => 'CLIN', 'name' => 'CLINICAL DEPARTMENTS'],
            ['code' => 'CNU',  'name' => 'CLINICAL NURSING UNITS'],
            ['code' => 'DR',   'name' => 'DELIVERY ROOM'],
            ['code' => 'DS',   'name' => 'DENTAL SERVICES'],
            ['code' => 'PATH', 'name' => 'DEPARTMENT OF PATHOLOGY'],
            ['code' => 'RAD',  'name' => 'DEPARTMENT OF RADIOLOGY'],
            ['code' => 'EMD',  'name' => 'EMERGENCY MEDICINE DEPARTMENT'],
            ['code' => 'ENG',  'name' => 'ENGINEERING AND FACILITIES MANAGEMENT'],
            ['code' => 'HIM',  'name' => 'HEALTH INFORMATION'],
            ['code' => 'HK',   'name' => 'HOUSEKEEPING/LAUNDRY'],
            ['code' => 'HMB',  'name' => 'HUMAN MILK BANK'],
            ['code' => 'HR',   'name' => 'HUMAN RESOURCE MANAGEMENT'],
            ['code' => 'ICU',  'name' => 'INTENSIVE CARE UNIT'],
            ['code' => 'MM',   'name' => 'MATERIALS MANAGEMENT'],
            ['code' => 'MED',  'name' => 'MEDICAL SERVICE'],
            ['code' => 'MSW',  'name' => 'MEDICAL SOCIAL WORK'],
            ['code' => 'NICU', 'name' => 'NEONATAL INTENSIVE CARE UNIT (NICU)'],
            ['code' => 'ND',   'name' => 'NUTRITION AND DIETETICS'],
            ['code' => 'AAO',  'name' => 'OFFICE OF THE ADMINISTRATIVE OFFICER'],
            ['code' => 'CN',   'name' => 'OFFICE OF THE CHIEF NURSE'],
            ['code' => 'CH',   'name' => 'OFFICE OF THE CHIEF OF HOSPITAL'],
            ['code' => 'OR',   'name' => 'OPERATING ROOM'],
            ['code' => 'OPU',  'name' => 'OUT-PATIENT UNIT'],
            ['code' => 'PH',   'name' => 'PHARMACY'],
            ['code' => 'PROC', 'name' => 'PROCUREMENT'],
            ['code' => 'PRU',  'name' => 'PULMONARY/RESPIRATORY UNIT'],
            ['code' => 'SCA',  'name' => 'SPECIAL CARE AREAS'],
            ['code' => 'SCA-PACU', 'name' => 'SPECIAL CARE AREAS - POST ANESTHESIA CARE UNIT'],
        ];

        $now = now();

        DB::table('divisions')->insert(
            array_map(fn ($d) => [
                'code' => $d['code'],
                'name' => $d['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $divisions)
        );
    }
}
