<?php

return [

    'hide_date_selection' => ['monetization'],

    'types' => [

        'vacation' => [
            'fields' => [
                'destination' => 'required|in:within_ph,abroad',
                'purposeOfTravel' => 'required|string|max:1000',
            ],
            'required_documents' => [],
        ],

        'mandatory_forced' => [
            'fields' => [],
            'required_documents' => [],
        ],

        'sick' => [
            'fields' => [
                'hospitalName' => 'nullable|string|max:255',
                'physicianName' => 'nullable|string|max:255',
            ],
            'required_documents' => [
                'medical_certificate' => false,
            ],
        ],

        'maternity' => [
            'fields' => [
                'expectedDeliveryDate' => 'required|date',
                'numberOfChildren' => 'required|integer|min:1|max:10',
            ],
            'required_documents' => [
                'ultrasound' => true,
                'doctor_certificate' => true,
            ],
            'max_days' => 105,
        ],

        'paternity' => [
            'fields' => [],
            'required_documents' => [
                'birth_certificate' => true,
                'marriage_certificate' => true,
                'medical_certificate' => true,
            ],
            'max_days' => 7,
        ],

        'special_privilege' => [
            'fields' => [
                'specialPurpose' => 'required|string|max:1000',
                'locationType' => 'required|in:within_ph,abroad',
                'location' => 'nullable|string|max:255',
            ],
            'required_documents' => [],
        ],

        'solo_parent' => [
            'fields' => [],
            'required_documents' => [
                'solo_parent_id' => true,
            ],
            'max_days' => 7,
        ],

        'study' => [
            'fields' => [
                'schoolName' => 'required|string|max:255',
                'courseProgram' => 'required|string|max:255',
                'studyDuration' => 'required|string|max:100',
            ],
            'required_documents' => [
                'study_contract' => true,
            ],
        ],

        'vawc' => [
            'fields' => [
                'caseDescription' => 'required|string|max:2000',
            ],
            'required_documents_any_of' => [
                'bpo', 'tpo', 'ppo', 'barangay_cert', 'police_report', 'medical_certificate',
            ],
            'confidential' => true,
        ],

        'rehabilitation' => [
            'fields' => [
                'natureOfInjury' => 'required|string|max:1000',
                'rehabDuration' => 'required|string|max:100',
            ],
            'required_documents' => [
                'accident_report' => true,
                'medical_certificate' => true,
            ],
            'max_days_months' => 6,
        ],

        'special_women' => [
            'fields' => [
                'gynecologicalCondition' => 'required|string|max:500',
                'surgeryDate' => 'required|date',
                'recoveryPeriod' => 'required|string|max:100',
            ],
            'required_documents' => [
                'medical_certificate' => true,
                'clinical_summary' => true,
                'histopathological_report' => true,
                'operative_report' => true,
            ],
            'max_days_months' => 2,
        ],

        'calamity' => [
            'fields' => [
                'calamityType' => 'required|string|max:255',
                'affectedLocation' => 'required|string|max:255',
                'calamityDate' => 'required|date',
            ],
            'required_documents' => [
                'proof_residency' => true,
                'gov_verification' => true,
            ],
            'max_days' => 5,
            'filing_window_days' => 30,
        ],

        'monetization' => [
            'fields' => [
                'creditsToMonetize' => 'required|numeric|min:1',
                'monetizationJustification' => 'required|string|max:2000',
            ],
            'required_documents' => [],
        ],

        'terminal' => [
            'fields' => [
                'separationType' => 'required|in:resignation,retirement,separation',
            ],
            'required_documents' => [
                'separation_documents' => true,
            ],
        ],

        'adoption' => [
            'fields' => [
                'childName' => 'required|string|max:255',
                'placementDate' => 'required|date',
                'adoptionAgency' => 'required|string|max:255',
            ],
            'required_documents' => [
                'pre_adoptive_placement' => true,
            ],
        ],
    ],

    'uploads' => [
        'max_size_kb' => 10240,
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
        'disk' => 'leave_documents', 
    ],

    'accrual' => [
        'monthly_credit' => 1.25,
        'eligible_type_codes' => ['vacation', 'sick'],
    ],
];
