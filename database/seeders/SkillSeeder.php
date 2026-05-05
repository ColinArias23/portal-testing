<?php
 
namespace Database\Seeders;
 
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
 
class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
 
        $skills = [
            // Clinical Skills
            ['name' => 'Patient Assessment & Documentation', 'category' => 'clinical', 'weight' => 3, 'order' => 1],
            ['name' => 'Clinical Decision Making', 'category' => 'clinical', 'weight' => 3, 'order' => 2],
            ['name' => 'Infection Prevention & Control', 'category' => 'clinical', 'weight' => 3, 'order' => 3],
            ['name' => 'Patient Communication & Empathy', 'category' => 'clinical', 'weight' => 2, 'order' => 4],
            ['name' => 'Medication & Dosage Calculation', 'category' => 'clinical', 'weight' => 3, 'order' => 5],
            ['name' => 'Emergency Response & First Aid', 'category' => 'clinical', 'weight' => 3, 'order' => 6],
 
            // Administrative Skills
            ['name' => 'Data Entry & Records Management', 'category' => 'administrative', 'weight' => 2, 'order' => 7],
            ['name' => 'Scheduling & Appointment Management', 'category' => 'administrative', 'weight' => 2, 'order' => 8],
            ['name' => 'Billing & Insurance Processing', 'category' => 'administrative', 'weight' => 2, 'order' => 9],
            ['name' => 'Report Writing & Documentation', 'category' => 'administrative', 'weight' => 2, 'order' => 10],
            ['name' => 'Inventory Management', 'category' => 'administrative', 'weight' => 1, 'order' => 11],
 
            // Technical Skills
            ['name' => 'Electronic Health Records (EHR) System', 'category' => 'technical', 'weight' => 3, 'order' => 12],
            ['name' => 'Microsoft Office Suite', 'category' => 'technical', 'weight' => 1, 'order' => 13],
            ['name' => 'Medical Equipment Operation', 'category' => 'technical', 'weight' => 3, 'order' => 14],
            ['name' => 'Laboratory Equipment & Procedures', 'category' => 'technical', 'weight' => 3, 'order' => 15],
            ['name' => 'Data Analysis & Reporting Tools', 'category' => 'technical', 'weight' => 2, 'order' => 16],
 
            // Soft Skills
            ['name' => 'Teamwork & Collaboration', 'category' => 'soft', 'weight' => 2, 'order' => 17],
            ['name' => 'Leadership & Mentoring', 'category' => 'soft', 'weight' => 2, 'order' => 18],
            ['name' => 'Problem-Solving & Critical Thinking', 'category' => 'soft', 'weight' => 3, 'order' => 19],
            ['name' => 'Communication Skills', 'category' => 'soft', 'weight' => 3, 'order' => 20],
            ['name' => 'Conflict Resolution', 'category' => 'soft', 'weight' => 2, 'order' => 21],
            ['name' => 'Stress Management & Resilience', 'category' => 'soft', 'weight' => 2, 'order' => 22],
            ['name' => 'Time Management & Organization', 'category' => 'soft', 'weight' => 2, 'order' => 23],
 
            // Compliance & Governance
            ['name' => 'Health & Safety Regulations (OSHA/DOH)', 'category' => 'compliance', 'weight' => 3, 'order' => 24],
            ['name' => 'Patient Confidentiality & HIPAA Compliance', 'category' => 'compliance', 'weight' => 3, 'order' => 25],
            ['name' => 'Quality Assurance & Audit Practices', 'category' => 'compliance', 'weight' => 2, 'order' => 26],
            ['name' => 'Ethics & Professional Conduct', 'category' => 'compliance', 'weight' => 3, 'order' => 27],
            ['name' => 'Disaster Management & Preparedness', 'category' => 'compliance', 'weight' => 2, 'order' => 28],
        ];
 
        $rows = [];
        foreach ($skills as $skill) {
            $rows[] = [
                'name'           => $skill['name'],
                'description'    => null,
                'category'       => $skill['category'],
                'weight'         => $skill['weight'],
                'display_order'  => $skill['order'],
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }
 
        DB::table('skills')->insertOrIgnore($rows);
    }
}