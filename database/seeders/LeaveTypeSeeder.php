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
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Filing Requirement',
                    'message' => 'Leave should be filed at least 5 days before effectivity whenever possible.',
                ],
                'required_documents' => [
                    ['id' => 'travel_clearance', 'label' => 'Travel Authority / Clearance (if abroad)'],
                ],
            ],
            [
                'code' => 'mandatory_forced', 'name' => 'Mandatory / Forced Leave', 'color' => '#6366f1', 'bg_color' => '#eef2ff',
                'max_days' => 5, 'requires_balance_check' => true, 'sort_order' => 2,
                'description' => 'A required 5-day annual leave every employee must take from their vacation leave credits to promote employee wellness. Forfeited if not availed within the year.',
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Mandatory Leave Policy',
                    'message' => 'Annual 5-day mandatory leave. Forfeited if not taken during the year. Availment of vacation leave counts toward this requirement.',
                ],
                'required_documents' => [],
            ],
            [
                'code' => 'sick', 'name' => 'Sick Leave', 'color' => '#f59e0b', 'bg_color' => '#fffbeb',
                'is_accrual_based' => true, 'default_monthly_accrual' => 1.25, 'requires_balance_check' => true, 'sort_order' => 3,
                'description' => 'For absences due to illness, injury, or a medical/dental consultation, whether for the employee or requiring their personal attention.',
                'filing_guidelines' => [
                    'variant' => 'warning',
                    'title' => 'Document Requirements',
                    'message' => 'File immediately upon return. Medical certificate required if filed in advance or for more than 5 days. Affidavit required if no medical consultation was made.',
                ],
                'required_documents' => [
                    ['id' => 'medical_certificate', 'label' => 'Medical Certificate', 'required' => true],
                    ['id' => 'affidavit', 'label' => 'Affidavit (if no consultation)'],
                ],
            ],
            [
                'code' => 'maternity', 'name' => 'Maternity Leave', 'color' => '#ec4899', 'bg_color' => '#fdf2f8',
                'max_days' => 105, 'requires_balance_check' => false, 'sort_order' => 4,
                'eligible_genders' => ['FEMALE'],
                'description' => 'Paid leave for childbirth or pregnancy-related conditions, granted regardless of civil status or number of children.',
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Maximum Duration',
                    'message' => 'Maximum leave duration: 105 days',
                ],
                'required_documents' => [
                    ['id' => 'proof_pregnancy', 'label' => 'Proof of Pregnancy', 'required' => true],
                    ['id' => 'ultrasound', 'label' => 'Ultrasound', 'required' => true],
                    ['id' => 'doctor_certificate', 'label' => 'Doctor Certificate', 'required' => true],
                    ['id' => 'cs_form_6a', 'label' => 'CS Form No. 6a (if applicable)'],
                ],
            ],
            [
                'code' => 'paternity', 'name' => 'Paternity Leave', 'color' => '#8b5cf6', 'bg_color' => '#f5f3ff',
                'max_days' => 7, 'requires_balance_check' => false, 'sort_order' => 5,
                'eligible_genders' => ['MALE'],
                'description' => 'For a married male employee to support his legitimate spouse during childbirth or pregnancy-related complications.',
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Maximum Duration',
                    'message' => 'Maximum leave duration: 7 days',
                ],
                'required_documents' => [
                    ['id' => 'birth_certificate', 'label' => 'Birth Certificate', 'required' => true],
                    ['id' => 'marriage_certificate', 'label' => 'Marriage Certificate', 'required' => true],
                    ['id' => 'medical_certificate', 'label' => 'Medical Certificate', 'required' => true],
                ],
            ],
            [
                'code' => 'special_privilege', 'name' => 'Special Privilege Leave', 'color' => '#06b6d4', 'bg_color' => '#ecfeff',
                'max_days' => 3, 'annual_grant_days' => null, 'requires_balance_check' => false, 'enforce_annual_cap' => true, 'sort_order' => 6,
                'description' => 'A fixed 3-day annual entitlement (non-cumulative, not deducted from VL/SL) for personal milestones and circumstances - birthday, wedding anniversary, or other personal/family occasions.',
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Annual Entitlement',
                    'message' => 'A fixed 3 days per year, usable across one or more filings. File at least 1 week before leave whenever possible.',
                ],
                'required_documents' => [],
            ],
            [
                'code' => 'solo_parent', 'name' => 'Solo Parent Leave', 'color' => '#f97316', 'bg_color' => '#fff7ed',
                'max_days' => 7, 'requires_balance_check' => false, 'sort_order' => 7,
                'description' => 'For qualified solo parents to attend to parental duties and responsibilities.',
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Maximum Duration',
                    'message' => 'Maximum leave duration: 7 days',
                ],
                'required_documents' => [
                    ['id' => 'solo_parent_id', 'label' => 'Solo Parent Identification Card', 'required' => true],
                ],
            ],
            [
                'code' => 'study', 'name' => 'Study Leave', 'color' => '#10b981', 'bg_color' => '#ecfdf5',
                'max_days' => 180, 'requires_balance_check' => false, 'sort_order' => 8,
                'description' => "For completing a course of study or taking a board/bar examination relevant to the employee's work.",
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Study Leave Policy',
                    'message' => 'Up to 6 months. Must meet agency internal requirements and have a contract between the agency head and employee.',
                ],
                'required_documents' => [
                    ['id' => 'study_contract', 'label' => 'Study Leave Contract', 'required' => true],
                ],
            ],
            [
                'code' => 'vawc', 'name' => 'VAWC Leave', 'color' => '#dc2626', 'bg_color' => '#fef2f2',
                'is_confidential' => true, 'max_days' => 10, 'requires_balance_check' => false, 'sort_order' => 9,
                'description' => 'For women employees who are victims of violence, to attend to medical, legal, and related concerns. Handled with strict confidentiality.',
                'filing_guidelines' => [
                    'variant' => 'confidential',
                    'title' => 'Confidential Leave',
                    'message' => 'This leave type is handled with strict confidentiality. Accepted documents: BPO, TPO, PPO, certification from Punong Barangay/Prosecutor, or Police Report with Medical Certificate.',
                ],
                'required_documents' => [
                    ['id' => 'bpo', 'label' => 'Barangay Protection Order'],
                    ['id' => 'tpo', 'label' => 'Temporary Protection Order'],
                    ['id' => 'ppo', 'label' => 'Permanent Protection Order'],
                    ['id' => 'barangay_cert', 'label' => 'Certification from Punong Barangay/Prosecutor'],
                    ['id' => 'police_report', 'label' => 'Police Report'],
                    ['id' => 'medical_certificate', 'label' => 'Medical Certificate'],
                ],
            ],
            [
                'code' => 'rehabilitation', 'name' => 'Rehabilitation Leave', 'color' => '#14b8a6', 'bg_color' => '#f0fdfa',
                'max_days' => 180, 'requires_balance_check' => false, 'sort_order' => 10,
                'description' => 'For recovery from injuries sustained in the actual performance of official duty.',
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Maximum Duration',
                    'message' => 'Maximum leave duration: 6 months. Must be filed within 1 week from the time of the accident.',
                ],
                'required_documents' => [
                    ['id' => 'accident_report', 'label' => 'Accident / Police Report', 'required' => true],
                    ['id' => 'medical_certificate', 'label' => 'Medical Certificate', 'required' => true],
                    ['id' => 'gov_physician_concurrence', 'label' => 'Government Physician Concurrence'],
                ],
            ],
            [
                'code' => 'special_women', 'name' => 'Special Leave Benefits for Women', 'color' => '#d946ef', 'bg_color' => '#fdf4ff',
                'max_days' => 60, 'requires_balance_check' => false, 'sort_order' => 11,
                'description' => 'For women employees who undergo surgery for gynecological disorders, to allow for recovery.',
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Filing Requirement',
                    'message' => 'File at least 5 days prior to scheduled gynecological surgery (except in emergencies, then immediately upon return). Maximum duration: up to 2 months.',
                ],
                'required_documents' => [
                    ['id' => 'medical_certificate', 'label' => 'Medical Certificate', 'required' => true],
                    ['id' => 'clinical_summary', 'label' => 'Clinical Summary', 'required' => true],
                    ['id' => 'histopathological_report', 'label' => 'Histopathological Report', 'required' => true],
                    ['id' => 'operative_report', 'label' => 'Operative Report', 'required' => true],
                ],
            ],
            [
                'code' => 'calamity', 'name' => 'Special Emergency (Calamity) Leave', 'color' => '#64748b', 'bg_color' => '#f8fafc',
                'max_days' => 5, 'requires_balance_check' => false, 'sort_order' => 12,
                'description' => 'For employees directly affected by a natural or man-made calamity in a declared disaster area.',
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Maximum Duration',
                    'message' => 'Maximum leave duration: 5 days. Must be filed within 30 days from the actual occurrence.',
                ],
                'required_documents' => [
                    ['id' => 'proof_residency', 'label' => 'Proof of Residency', 'required' => true],
                    ['id' => 'gov_verification', 'label' => 'Government Verification Document', 'required' => true],
                ],
            ],
            [
                'code' => 'monetization', 'name' => 'Monetization of Leave Credits', 'color' => '#eab308', 'bg_color' => '#fefce8',
                'requires_date_range' => false, 'requires_balance_check' => true, 'sort_order' => 13,
                'description' => 'Converts a portion of accumulated, unused vacation/sick leave credits into a cash equivalent, without actually going on leave.',
                'filing_guidelines' => [
                    'variant' => 'warning',
                    'title' => 'Monetization Policy',
                    'message' => 'For monetization of 50% or more of accumulated credits, a letter request to the head of the agency stating valid and justifiable reasons is required.',
                ],
                'required_documents' => [
                    ['id' => 'letter_request', 'label' => 'Letter Request to Agency Head', 'required' => true],
                ],
            ],
            [
                'code' => 'terminal', 'name' => 'Terminal Leave', 'color' => '#475569', 'bg_color' => '#f1f5f9',
                'requires_balance_check' => true, 'sort_order' => 14,
                'description' => 'Cashes out all remaining leave credits upon resignation, retirement, or separation from service.',
                'filing_guidelines' => [
                    'variant' => 'warning',
                    'title' => 'Clearance Required',
                    'message' => 'For terminal leave, application must be accompanied by clearance from money, property, and work-related accountabilities.',
                ],
                'required_documents' => [
                    ['id' => 'separation_documents', 'label' => 'Proof of Resignation/Retirement/Separation', 'required' => true],
                    ['id' => 'clearance', 'label' => 'Money, Property & Work Clearance', 'required' => true],
                ],
            ],
            [
                'code' => 'adoption', 'name' => 'Adoption Leave', 'color' => '#0ea5e9', 'bg_color' => '#f0f9ff',
                'max_days' => 60, 'requires_balance_check' => false, 'sort_order' => 15,
                'description' => 'For an employee upon the issuance of a Pre-Adoptive Placement Authority by DSWD in their favor.',
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Adoption Leave',
                    'message' => 'Requires authenticated copy of Pre-Adoptive Placement Authority issued by DSWD.',
                ],
                'required_documents' => [
                    ['id' => 'pre_adoptive_placement', 'label' => 'Pre-Adoptive Placement Authority (DSWD)', 'required' => true],
                ],
            ],
            [
                'code' => 'wellness', 'name' => 'Wellness Leave', 'color' => '#22c55e', 'bg_color' => '#f0fdf4',
                'requires_balance_check' => true, 'sort_order' => 16,
                'description' => "For attending to the employee's physical and mental wellbeing - medical/wellness check-ups, therapy sessions, or similar self-care activities.",
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Filing Requirement',
                    'message' => 'File at least 3 days before the intended date whenever possible. Subject to available Wellness Leave credits.',
                ],
                'required_documents' => [],
            ],
            [
                'code' => 'compensatory_off', 'name' => 'Compensatory Off', 'color' => '#a855f7', 'bg_color' => '#faf5ff',
                'requires_balance_check' => true, 'unit' => 'hours', 'sort_order' => 17,
                'description' => 'Time off earned in exchange for approved overtime work rendered, credited and consumed in hours.',
                'filing_guidelines' => [
                    'variant' => 'info',
                    'title' => 'Compensatory Off Policy',
                    'message' => 'Credited in hours from approved overtime rendered. Must be filed against available Compensatory Off credits and used within the period set by HR.',
                ],
                'required_documents' => [
                    ['id' => 'ot_approval', 'label' => 'Approved Overtime Authorization/DTR (basis for hours credited)'],
                ],
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