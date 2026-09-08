<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    private const ALL_TYPES = ['Plantilla', 'Contract of Service', 'Consultant'];
    private const NON_PLANTILLA_ELIGIBLE = ['vacation', 'sick', 'maternity', 'paternity'];

    public function run(): void
    {
        $types = [
            ['code' => 'vacation', 'name' => 'Vacation Leave', 'color' => '#3b82f6', 'bg_color' => '#eff6ff', 'is_accrual_based' => true, 'default_monthly_accrual' => 1.25, 'requires_balance_check' => true, 'sort_order' => 1],
            ['code' => 'mandatory_forced', 'name' => 'Mandatory / Forced Leave', 'color' => '#6366f1', 'bg_color' => '#eef2ff', 'max_days' => 5, 'requires_balance_check' => true, 'sort_order' => 2],
            ['code' => 'sick', 'name' => 'Sick Leave', 'color' => '#f59e0b', 'bg_color' => '#fffbeb', 'is_accrual_based' => true, 'default_monthly_accrual' => 1.25, 'requires_balance_check' => true, 'sort_order' => 3],
            ['code' => 'maternity', 'name' => 'Maternity Leave', 'color' => '#ec4899', 'bg_color' => '#fdf2f8', 'max_days' => 105, 'requires_balance_check' => false, 'sort_order' => 4],
            ['code' => 'paternity', 'name' => 'Paternity Leave', 'color' => '#8b5cf6', 'bg_color' => '#f5f3ff', 'max_days' => 7, 'requires_balance_check' => false, 'sort_order' => 5],
            ['code' => 'special_privilege', 'name' => 'Special Privilege Leave', 'color' => '#06b6d4', 'bg_color' => '#ecfeff', 'max_days' => 3, 'requires_balance_check' => true, 'sort_order' => 6],
            ['code' => 'solo_parent', 'name' => 'Solo Parent Leave', 'color' => '#f97316', 'bg_color' => '#fff7ed', 'max_days' => 7, 'requires_balance_check' => false, 'sort_order' => 7],
            ['code' => 'study', 'name' => 'Study Leave', 'color' => '#10b981', 'bg_color' => '#ecfdf5', 'max_days' => 180, 'requires_balance_check' => false, 'sort_order' => 8],
            ['code' => 'vawc', 'name' => 'VAWC Leave', 'color' => '#dc2626', 'bg_color' => '#fef2f2', 'is_confidential' => true, 'max_days' => 10, 'requires_balance_check' => false, 'sort_order' => 9],
            ['code' => 'rehabilitation', 'name' => 'Rehabilitation Leave', 'color' => '#14b8a6', 'bg_color' => '#f0fdfa', 'max_days' => 180, 'requires_balance_check' => false, 'sort_order' => 10],
            ['code' => 'special_women', 'name' => 'Special Leave Benefits for Women', 'color' => '#d946ef', 'bg_color' => '#fdf4ff', 'max_days' => 60, 'requires_balance_check' => false, 'sort_order' => 11],
            ['code' => 'calamity', 'name' => 'Special Emergency (Calamity) Leave', 'color' => '#64748b', 'bg_color' => '#f8fafc', 'max_days' => 5, 'requires_balance_check' => false, 'sort_order' => 12],
            ['code' => 'monetization', 'name' => 'Monetization of Leave Credits', 'color' => '#eab308', 'bg_color' => '#fefce8', 'requires_date_range' => false, 'requires_balance_check' => true, 'sort_order' => 13],
            ['code' => 'terminal', 'name' => 'Terminal Leave', 'color' => '#475569', 'bg_color' => '#f1f5f9', 'requires_balance_check' => true, 'sort_order' => 14],
            ['code' => 'adoption', 'name' => 'Adoption Leave', 'color' => '#0ea5e9', 'bg_color' => '#f0f9ff', 'max_days' => 60, 'requires_balance_check' => false, 'sort_order' => 15],
        ];

        foreach ($types as &$type) {
            $type['eligible_employment_types'] = in_array($type['code'], self::NON_PLANTILLA_ELIGIBLE, true)
                ? self::ALL_TYPES
                : ['Plantilla'];
        }
        unset($type);

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}