<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    private const ALL_TYPES = ['Plantilla', 'Contract of Service', 'Consultant'];

    private const SHARED_ELIGIBLE = ['vacation', 'sick', 'wellness', 'compensatory_off'];


    public function run(): void
    {
        $types = [
            [
                'code' => 'vacation', 'name' => 'Vacation Leave', 'color' => '#3b82f6', 'bg_color' => '#eff6ff',
                'is_accrual_based' => true, 'default_monthly_accrual' => 1.25, 'requires_balance_check' => true, 'sort_order' => 1,
                'description' => 'For planned personal time off - travel, rest, or attending to personal matters. Earned monthly and accumulates if unused.',
            ],
            [
                'code' => 'mandatory_forced', 'name' => 'Mandatory / Forced Leave', 'color' => '#6366f1', 'bg_color' => '#eef2ff',
                'max_days' => 5, 'requires_balance_check' => true, 'sort_order' => 2,
                'description' => 'A required 5-day annual leave every employee must take from their vacation leave credits to promote employee wellness. Forfeited if not availed within the year.',
            ],
            [
                'code' => 'sick', 'name' => 'Sick Leave', 'color' => '#f59e0b', 'bg_color' => '#fffbeb',
                'is_accrual_based' => true, 'default_monthly_accrual' => 1.25, 'requires_balance_check' => true, 'sort_order' => 3,
                'description' => 'For absences due to illness, injury, or a medical/dental consultation, whether for the employee or requiring their personal attention.',
            ],
            [
                'code' => 'maternity', 'name' => 'Maternity Leave', 'color' => '#ec4899', 'bg_color' => '#fdf2f8',
                'max_days' => 105, 'requires_balance_check' => false, 'sort_order' => 4,
                'description' => 'Paid leave for childbirth or pregnancy-related conditions, granted regardless of civil status or number of children.',
            ],
            [
                'code' => 'paternity', 'name' => 'Paternity Leave', 'color' => '#8b5cf6', 'bg_color' => '#f5f3ff',
                'max_days' => 7, 'requires_balance_check' => false, 'sort_order' => 5,
                'description' => 'For a married male employee to support his legitimate spouse during childbirth or pregnancy-related complications.',
            ],
            [
                'code' => 'special_privilege', 'name' => 'Special Privilege Leave', 'color' => '#06b6d4', 'bg_color' => '#ecfeff',
                'max_days' => 3, 'requires_balance_check' => true, 'sort_order' => 6,
                'description' => 'For personal milestones and circumstances - birthday, wedding anniversary, or other personal/family occasions.',
            ],
            [
                'code' => 'solo_parent', 'name' => 'Solo Parent Leave', 'color' => '#f97316', 'bg_color' => '#fff7ed',
                'max_days' => 7, 'requires_balance_check' => false, 'sort_order' => 7,
                'description' => 'For qualified solo parents to attend to parental duties and responsibilities.',
            ],
            [
                'code' => 'study', 'name' => 'Study Leave', 'color' => '#10b981', 'bg_color' => '#ecfdf5',
                'max_days' => 180, 'requires_balance_check' => false, 'sort_order' => 8,
                'description' => "For completing a course of study or taking a board/bar examination relevant to the employee's work.",
            ],
            [
                'code' => 'vawc', 'name' => 'VAWC Leave', 'color' => '#dc2626', 'bg_color' => '#fef2f2',
                'is_confidential' => true, 'max_days' => 10, 'requires_balance_check' => false, 'sort_order' => 9,
                'description' => 'For women employees who are victims of violence, to attend to medical, legal, and related concerns. Handled with strict confidentiality.',
            ],
            [
                'code' => 'rehabilitation', 'name' => 'Rehabilitation Leave', 'color' => '#14b8a6', 'bg_color' => '#f0fdfa',
                'max_days' => 180, 'requires_balance_check' => false, 'sort_order' => 10,
                'description' => 'For recovery from injuries sustained in the actual performance of official duty.',
            ],
            [
                'code' => 'special_women', 'name' => 'Special Leave Benefits for Women', 'color' => '#d946ef', 'bg_color' => '#fdf4ff',
                'max_days' => 60, 'requires_balance_check' => false, 'sort_order' => 11,
                'description' => 'For women employees who undergo surgery for gynecological disorders, to allow for recovery.',
            ],
            [
                'code' => 'calamity', 'name' => 'Special Emergency (Calamity) Leave', 'color' => '#64748b', 'bg_color' => '#f8fafc',
                'max_days' => 5, 'requires_balance_check' => false, 'sort_order' => 12,
                'description' => 'For employees directly affected by a natural or man-made calamity in a declared disaster area.',
            ],
            [
                'code' => 'monetization', 'name' => 'Monetization of Leave Credits', 'color' => '#eab308', 'bg_color' => '#fefce8',
                'requires_date_range' => false, 'requires_balance_check' => true, 'sort_order' => 13,
                'description' => 'Converts a portion of accumulated, unused vacation/sick leave credits into a cash equivalent, without actually going on leave.',
            ],
            [
                'code' => 'terminal', 'name' => 'Terminal Leave', 'color' => '#475569', 'bg_color' => '#f1f5f9',
                'requires_balance_check' => true, 'sort_order' => 14,
                'description' => 'Cashes out all remaining leave credits upon resignation, retirement, or separation from service.',
            ],
            [
                'code' => 'adoption', 'name' => 'Adoption Leave', 'color' => '#0ea5e9', 'bg_color' => '#f0f9ff',
                'max_days' => 60, 'requires_balance_check' => false, 'sort_order' => 15,
                'description' => 'For an employee upon the issuance of a Pre-Adoptive Placement Authority by DSWD in their favor.',
            ],
            [
                'code' => 'wellness', 'name' => 'Wellness Leave', 'color' => '#22c55e', 'bg_color' => '#f0fdf4',
                'requires_balance_check' => true, 'sort_order' => 16,
                'description' => "For attending to the employee's physical and mental wellbeing - medical/wellness check-ups, therapy sessions, or similar self-care activities.",
            ],
            [
                'code' => 'compensatory_off', 'name' => 'Compensatory Off', 'color' => '#a855f7', 'bg_color' => '#faf5ff',
                'requires_balance_check' => true, 'unit' => 'hours', 'sort_order' => 17,
                'description' => 'Time off earned in exchange for approved overtime work rendered, credited and consumed in hours.',
            ],
        ];

        foreach ($types as &$type) {
            $type['unit'] = $type['unit'] ?? 'days';

            if (in_array($type['code'], self::SHARED_ELIGIBLE, true)) {
                $type['eligible_employment_types'] = self::ALL_TYPES;
            } else {
                $type['eligible_employment_types'] = ['Plantilla'];
            }
        }
        unset($type);

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}