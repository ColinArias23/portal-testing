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
            ['division_code' => 'MCC','code' => 'MC-CHIEF', 'name' => 'MEDICAL CENTER CHIEF','type' => 'DEPARTMENT', 'head_employee_id' => 1],
            ['division_code' => 'HOPSS','code' => 'ACCT', 'name' => 'ACCOUNTING','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'HOPSS','code' => 'ADM',  'name' => 'ADMITTING','type' => 'DEPARTMENT', 'head_employee_id' => 73],
            ['division_code' => 'MED','code' => 'ACL',  'name' => 'ANATOMIC AND CLINICAL LABORATORY','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'HOPSS','code' => 'BILL', 'name' => 'BILLING AND CLAIMS','type' => 'DEPARTMENT', 'head_employee_id' => 103],
            ['division_code' => 'MED','code' => 'BB',   'name' => 'BLOOD BANK','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'HOPSS','code' => 'BUD',  'name' => 'BUDGET','type' => 'DEPARTMENT', 'head_employee_id' => 101],
            ['division_code' => 'HOPSS','code' => 'CASH', 'name' => 'CASH MANAGEMENT','type' => 'DEPARTMENT', 'head_employee_id' => 63],
            ['division_code' => 'CN','code' => 'CSS',  'name' => 'CENTRAL SUPPLY AND STERILIZATION','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'MED','code' => 'CLIN', 'name' => 'CLINICAL DEPARTMENTS','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'CN','code' => 'CNU',  'name' => 'CLINICAL NURSING','type' => 'UNIT', 'head_employee_id' => null],
            ['division_code' => 'CN','code' => 'DR',   'name' => 'DELIVERY ROOM','type' => 'DEPARTMENT', 'head_employee_id' => null],      
            ['division_code' => 'MED','code' => 'DS',   'name' => 'DENTAL SERVICES','type' => 'UNIT', 'head_employee_id' => 197],
            ['division_code' => 'MED','code' => 'PATH', 'name' => 'DEPARTMENT OF PATHOLOGY','type' => 'DEPARTMENT', 'head_employee_id' => 99],
            ['division_code' => 'MED','code' => 'RAD',  'name' => 'DEPARTMENT OF RADIOLOGY','type' => 'DEPARTMENT', 'head_employee_id' => 279],
            ['division_code' => 'MED','code' => 'EMD',  'name' => 'EMERGENCY MEDICINE DEPARTMENT','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'HOPSS','code' => 'ENG',  'name' => 'FACILITIES MANAGEMENT','type' => 'DEPARTMENT', 'head_employee_id' => 146],
            ['division_code' => 'HOPSS','code' => 'ENGR', 'name' => 'ENGINEERING','type' => 'DEPARTMENT', 'head_employee_id' => 440],
            ['division_code' => 'MED','code' => 'HIM',  'name' => 'HEALTH INFORMATION MANAGEMENT','type' => 'DEPARTMENT', 'head_employee_id' => 200],
            ['division_code' => 'HOPSS','code' => 'HK',   'name' => 'HOUSEKEEPING/LAUNDRY','type' => 'DEPARTMENT', 'head_employee_id' => 40],
            ['division_code' => 'MED','code' => 'HMB',  'name' => 'HUMAN MILK BANK','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'HOPSS','code' => 'HR',   'name' => 'HUMAN RESOURCE MANAGEMENT','type' => 'DEPARTMENT', 'head_employee_id' => 198],
            ['division_code' => 'CN','code' => 'ICU',  'name' => 'INTENSIVE CARE','type' => 'UNIT', 'head_employee_id' => null],
            ['division_code' => 'HOPSS','code' => 'MM',   'name' => 'MATERIALS MANAGEMENT','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'MED','code' => 'MED',  'name' => 'MEDICAL SERVICE','type' => 'DEPARTMENT', 'head_employee_id' => 55],
            ['division_code' => 'CN','code' => 'NICU', 'name' => 'NEONATAL INTENSIVE CARE (NICU)','type' => 'DEPARTMENT', 'head_employee_id' => 230],
            ['division_code' => 'MED','code' => 'ND',   'name' => 'NUTRITION AND DIETETICS','type' => 'UNIT', 'head_employee_id' => 365],
            ['division_code' => 'HOPSS','code' => 'HOPSS',  'name' => 'OFFICE OF THE ADMINISTRATIVE OFFICER','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'CN','code' => 'CN',   'name' => 'OFFICE OF THE CHIEF NURSE','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'CH','code' => 'CH',   'name' => 'OFFICE OF THE CHIEF OF HOSPITAL','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'CN','code' => 'OR',   'name' => 'OPERATING ROOM','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'MED','code' => 'OPU',  'name' => 'OUT-PATIENT','type' => 'UNIT', 'head_employee_id' => 133],
            ['division_code' => 'MED','code' => 'PH',   'name' => 'PHARMACY','type' => 'DEPARTMENT', 'head_employee_id' => 169],
            ['division_code' => 'HOPSS','code' => 'PROC/WARE/INVEN', 'name' => 'PROCUREMENT/WAREHOUS/INVENTORY','type' => 'DEPARTMENT', 'head_employee_id' => 199],
            ['division_code' => 'CN','code' => 'PRU',  'name' => 'PULMONARY/RESPIRATORY','type' => 'UNIT', 'head_employee_id' => 164],
            ['division_code' => 'MED','code' => 'SCA',  'name' => 'SPECIAL CARE AREAS','type' => 'DEPARTMENT', 'head_employee_id' => null],
            ['division_code' => 'CN','code' => 'SCA-PACU', 'name' => 'SPECIAL CARE AREAS - POST ANESTHESIA CARE','type' => 'UNIT', 'head_employee_id' => 152],
            ['division_code' => 'MED','code' => 'IM', 'name' => 'INTERNAL MEDICINE','type' => 'DEPARTMENT', 'head_employee_id' => 189],
            ['division_code' => 'MED','code' => 'FM', 'name' => 'FAMILY MEDICINE','type' => 'DEPARTMENT', 'head_employee_id' => 36],
            ['division_code' => 'MED','code' => 'PEDIA', 'name' => 'PEDIATRICS','type' => 'DEPARTMENT', 'head_employee_id' => 182],
            ['division_code' => 'MED','code' => 'HD', 'name' => 'HEMODIALYSIS','type' => 'DEPARTMENT', 'head_employee_id' => 241],
            ['division_code' => 'MED','code' => 'PICU', 'name' => 'PEDIATRICS INTENSIVE CARE','type' => 'UNIT', 'head_employee_id' => 260],
            ['division_code' => 'MED','code' => 'IMCU', 'name' => 'INTERMEDIATE CARE','type' => 'UNIT', 'head_employee_id' => 5],
            ['division_code' => 'MED','code' => 'MICU', 'name' => 'MEDICAL INTENSIVE CARE','type' => 'UNIT', 'head_employee_id' => 5],
            ['division_code' => 'MED','code' => 'SURGICAL', 'name' => 'SURGICAL SERVICES','type' => 'DEPARTMENT', 'head_employee_id' => 106],
            ['division_code' => 'MED','code' => 'SURGERY', 'name' => 'SURGERY','type' => 'DEPARTMENT', 'head_employee_id' => 106],
            ['division_code' => 'MED','code' => 'OB-GYN', 'name' => 'OBSTETRICS & GYNECOLOGY','type' => 'DEPARTMENT', 'head_employee_id' => 161],
            ['division_code' => 'MED','code' => 'ANES', 'name' => 'ANESTHESIOLOGY','type' => 'DEPARTMENT', 'head_employee_id' => 160],
            ['division_code' => 'MED','code' => 'ANC', 'name' => 'ANCILLARY SERVICES','type' => 'DEPARTMENT', 'head_employee_id' => 99],
            ['division_code' => 'MED','code' => 'HS', 'name' => 'HEART STATION','type' => 'DEPARTMENT', 'head_employee_id' => 5],
            ['division_code' => 'HOPSS','code' => 'PROPERTY', 'name' => 'PROPERTY MANAGEMENT','type' => 'DEPARTMENT', 'head_employee_id' => 139],
            ['division_code' => 'HOPSS','code' => 'D&P', 'name' => 'DESIGN & PLANNING','type' => 'DEPARTMENT', 'head_employee_id' => 146],
            ['division_code' => 'HOPSS','code' => 'SEC&P', 'name' => 'SECURITY','type' => 'DEPARTMENT', 'head_employee_id' => 361],
            ['division_code' => 'HOPSS','code' => 'MEDREC', 'name' => 'MEDICAL RECORDS','type' => 'DEPARTMENT', 'head_employee_id' => 111],
            ['division_code' => 'HOPSS','code' => 'IT', 'name' => 'INFORMATION TECHNOLOGY','type' => 'DEPARTMENT', 'head_employee_id' => 85],
            ['division_code' => 'HOPSS','code' => 'DP', 'name' => 'DATA PROTECTION','type' => 'DEPARTMENT', 'head_employee_id' => 189],
            ['division_code' => 'HOPSS','code' => 'CRM', 'name' => 'CUSTOMER RELATIONS MANAGEMENT','type' => 'DEPARTMENT', 'head_employee_id' => 134],
            ['division_code' => 'HOPSS','code' => 'PCS', 'name' => 'PATIENT CARE SUPPORT SERVICES CLUSTER','type' => 'DEPARTMENT', 'head_employee_id' => 158],
            ['division_code' => 'HOPSS','code' => 'MSS', 'name' => 'MEDICAL SOCIAL SERVICE','type' => 'DEPARTMENT', 'head_employee_id' => 138],
            ['division_code' => 'HOPSS','code' => 'DIET', 'name' => 'DIETARY','type' => 'DEPARTMENT', 'head_employee_id' => 12],
            ['division_code' => 'FD','code' => 'FS', 'name' => 'FINANCE SERVICES','type' => 'DEPARTMENT', 'head_employee_id' => 114],
            ['division_code' => 'CN','code' => 'PS', 'name' => 'PATIENT SERVICES','type' => 'DEPARTMENT', 'head_employee_id' => 91],
            ['division_code' => 'CN','code' => 'ADMIN', 'name' => 'ADMINISTRATION','type' => 'DEPARTMENT', 'head_employee_id' => 100],
            ['division_code' => 'CN','code' => 'TRAIN', 'name' => 'TRAINING','type' => 'DEPARTMENT', 'head_employee_id' => 100],
            ['division_code' => 'CN','code' => 'IP&CNS', 'name' => 'INFECTION PREVENTION & CONTROL NURSE','type' => 'DEPARTMENT', 'head_employee_id' => 100],
            ['division_code' => 'CN','code' => 'NQ&AN', 'name' => 'NURSING QUALITY & ASSURANCE NURSE','type' => 'DEPARTMENT', 'head_employee_id' => 472],
            ['division_code' => 'CH','code' => 'FIN', 'name' => 'FINANCE DEPARTMENT','type' => 'DEPARTMENT', 'head_employee_id' => 7],
            ['division_code' => 'MED','code' => 'MED-DIR', 'name' => 'MEDICAL DIRECTOR','type' => 'DEPARTMENT', 'head_employee_id' => 5],
        ];


         $now = now();

        $rows = [];
        foreach ($departments as $d) {
            $div = Division::where('code', $d['division_code'])->first();
            if (!$div) continue;

            $head = $d['head_employee_id'] ?? null;
            $head = ($head === '' ? null : $head);

            $rows[] = [
                'division_id' => $div->id,
                'type' => $d['type'] ?? 'DEPARTMENT',
                'code' => $d['code'],
                'name' => $d['name'],
                'description' => $d['description'] ?? null,
                'head_employee_id' => $head,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('departments')->upsert(
            $rows,
            ['division_id', 'code'],
            ['type', 'name', 'description', 'head_employee_id', 'updated_at']
        );
    }
}