<?php

namespace Database\Seeders;

use App\Models\LeaveApprovalFlowStep;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveApprovalFlowSeeder extends Seeder
{
    private const HR_ROLE = 'HR';
    private const MCC_ROLE = 'Medical Center Chief';

    private const STANDARD_CHAIN = [
        ['approver_source' => 'management_chain', 'label' => 'Management Approval', 'is_required' => true],
        ['approver_source' => 'role', 'approver_role' => self::HR_ROLE, 'label' => 'HR', 'is_required' => true],
        ['approver_source' => 'role', 'approver_role' => self::MCC_ROLE, 'label' => 'MCC', 'is_required' => true],
    ];

    public function run(): void
    {
        $standardChainTypes = [
            'vacation', 'mandatory_forced', 'sick', 'maternity', 'paternity',
            'special_privilege', 'solo_parent', 'study', 'rehabilitation',
            'special_women', 'calamity', 'adoption', 'monetization', 'terminal',
        ];

        foreach ($standardChainTypes as $code) {
            $this->buildFlow($code, self::STANDARD_CHAIN);
        }

        $this->buildFlow('vawc', [
            ['approver_source' => 'role', 'approver_role' => self::HR_ROLE, 'label' => 'HR (Confidential)', 'is_required' => true],
        ]);
    }

    private function buildFlow(string $leaveTypeCode, array $steps): void
    {
        $leaveType = LeaveType::where('code', $leaveTypeCode)->first();

        if (! $leaveType) {
            $this->command?->warn("Skipping approval flow for unknown leave type '{$leaveTypeCode}'. Run LeaveTypeSeeder first.");
            return;
        }

        LeaveApprovalFlowStep::where('leave_type_id', $leaveType->id)->delete();

        foreach ($steps as $order => $step) {
            LeaveApprovalFlowStep::create([
                'leave_type_id' => $leaveType->id,
                'step_order' => $order + 1,
                'approver_source' => $step['approver_source'],
                'approver_role' => $step['approver_role'] ?? null,
                'approver_user_id' => $step['approver_user_id'] ?? null,
                'is_required' => $step['is_required'] ?? true,
                'label' => $step['label'] ?? null,
            ]);
        }
    }
}