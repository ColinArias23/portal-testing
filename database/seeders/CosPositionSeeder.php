<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CosPositionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $positions = [
            'ACCOUNTANT',
            'ACCOUNTING CLERK',
            'ADMINISTRATIVE AIDE',
            'ADMITTING CLERK',
            'AIRCON TECHNICIAN',
            'BILLING CLERK',
            'CARPENTER',
            'CASHIER',
            'COOK',
            'DATA CONTROLLER',
            'DIETITIAN/NUTRITIONIST',
            'DRIVER',
            'ELECTRICIAN',
            'ENCODER',
            'FOOD SERVER',
            'KITCHEN HELPER',
            'LABORATORY AIDE',
            'LINEN ATTENDANT',
            'MECHANICAL ENGINEER',
            'MEDICAL LABORATORY TECHNICIAN',
            'MEDICAL TECHNOLOGIST',
            'MIDWIFE',
            'NURSE',
            'NURSE (ATTORNEY)',
            'NURSE (DIALYSIS)',
            'NURSING AIDE',
            'NURSING AIDE (DIALYSIS)',
            'NURSING AIDE (IW)',
            'NURSING ASSISTANT',
            'NUTRIONIST/DIETITIAN',
            'NUTRITIONIST-DIETITIAN',
            'PHARMACIST',
            'PHARMACY AIDE',
            'PHILHEALTH CLERK',
            'PHILHEALTH OFFICER',
            'PSYCHOMETRICIAN',
            'RADIOLOGIC TECHNOLOGIST',
            'RESPIRATORY THERAPIST',
            'SANITARY ENGINEER',
            'SOCIAL WELFARE ASSISTANT',
            'SOCIAL WELFARE OFFICER',
            'UTILITY WORKER',
        ];

        $rows = [];
        foreach ($positions as $title) {
            $rows[] = [
                'title'      => $title,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('cos_positions')->insertOrIgnore($rows);
    }
}