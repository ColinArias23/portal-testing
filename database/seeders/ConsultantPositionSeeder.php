<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ConsultantPositionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $positions = [
            'Medical Officer',
            'Medical Officer (20 hours)',
            'Medical Officer (Dialysis)',
            'Medical Officer (GP)',
            'Medical Specialist',
            'Medical Specialist (Adult Neurologist)',
            'Medical Specialist (Cardiologist)',
            'Medical Specialist (Clinical Nutrition)',
            'Medical Specialist (Dermatologist)',
            'Medical Specialist (Endocrinologist)',
            'Medical Specialist (Gastroenterology)',
            'Medical Specialist (General Pediatrician)',
            'Medical Specialist (Geriatrics)',
            'Medical Specialist (Infectious Disease)',
            'Medical Specialist (Neonatologist)',
            'Medical Specialist (Nephrologist)',
            'Medical Specialist (Ophthalmologist)',
            'Medical Specialist (Ortho-Surgeon)',
            'Medical Specialist (Pathologist)',
            'Medical Specialist (Pediatric Cardiologist)',
            'Medical Specialist (Pediatric Gastroenterologist)',
            'Medical Specialist (Pediatric Intensivist)',
            'Medical Specialist (Pediatric Nephrologist)',
            'Medical Specialist (Pediatric Neurologist)',
            'Medical Specialist (Pediatric Pulmonologist)',
            'Medical Specialist (Pulmonologist)',
            'Medical Specialist (Urologist)',
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

        DB::table('consultant_positions')->insertOrIgnore($rows);
    }
}