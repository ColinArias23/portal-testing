<?php

namespace App\Http\Resources\Recruitment\PlantillaPosting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicantQualificationResource extends JsonResource
{
    private const QUALIFICATION_DOCUMENT_KEYS = ['pds', 'eligibility', 'transcript', 'diploma', 'certificates'];

    public function toArray(Request $request): array
    {
        $application = $this->resource;
        $employee = $application->employee;
        $posting = $application->posting;

        return [
            'application_id' => $application->id,
            'applicant' => [
                'employee_id' => $employee?->id,
                'name' => $employee?->full_name,
                'employee_number' => $employee?->employee_number,
                'present_position' => $employee?->position_title,
            ],
            'position' => $this->formatPosition($posting),
            'qualifications' => [
                'eligibility' => $employee?->pdsEligibilities
                    ->map(fn ($row) => [
                        'id' => $row->id,
                        'eligibility' => $row->eligibility,
                        'rating' => $row->rating,
                        'exam_date' => $row->exam_date?->format('Y-m-d'),
                        'place' => $row->place,
                        'license_no' => $row->license_no,
                        'valid_until' => $row->valid_until?->format('Y-m-d'),
                    ])
                    ->values() ?? [],
                'education' => $employee?->pdsEducations
                    ->map(fn ($row) => [
                        'id' => $row->id,
                        'level' => $row->level,
                        'school' => $row->school,
                        'degree' => $row->degree,
                        'period_from' => $row->period_from,
                        'period_to' => $row->period_to,
                        'highest_level' => $row->highest_level,
                        'year_graduated' => $row->year_graduated,
                        'honors' => $row->honors,
                    ])
                    ->values() ?? [],
                'training' => $employee?->pdsTrainings
                    ->map(fn ($row) => [
                        'id' => $row->id,
                        'title' => $row->title,
                        'date_from' => $row->date_from?->format('Y-m-d'),
                        'date_to' => $row->date_to?->format('Y-m-d'),
                        'hours' => $row->hours,
                        'type' => $row->type,
                        'sponsored_by' => $row->sponsored_by,
                    ])
                    ->values() ?? [],
                'experience' => $employee?->pdsWorkExperiences
                    ->map(fn ($row) => [
                        'id' => $row->id,
                        'position' => $row->position,
                        'department' => $row->department,
                        'date_from' => $row->date_from?->format('Y-m-d'),
                        'date_to' => $row->date_to?->format('Y-m-d'),
                        'monthly_salary' => $row->monthly_salary,
                        'salary_grade' => $row->salary_grade,
                        'appointment_status' => $row->appointment_status,
                        'govt_service' => $row->govt_service,
                    ])
                    ->values() ?? [],
                'performance' => [
                    'available' => false,
                    'message' => 'Performance rating is not yet available. SPMS integration for this section has not been implemented.',
                ],
            ],
            'pds_on_file' => (bool) $employee?->hasPdsOnFile(),
            'remarks' => $application->remarks,
            'documents' => $application->documents
                ->whereIn('document_key', self::QUALIFICATION_DOCUMENT_KEYS)
                ->map(fn ($document) => [
                    'id' => $document->id,
                    'key' => $document->document_key,
                    'original_filename' => $document->original_filename,
                ])
                ->values(),
        ];
    }

    private function formatPosition($posting): ?array
    {
        if (! $posting) {
            return null;
        }

        return [
            'title' => $posting->title,
            'item_no' => $posting->base_item_number,
            'salary_grade' => $posting->salaryGrade?->salary_grade,
            'monthly_salary' => $posting->monthly_salary,
            'office' => $posting->department?->name,
            'division' => $posting->division?->name,
            'requirements' => [
                'eligibility' => $posting->qualification_eligibility,
                'education' => $posting->qualification_education,
                'training' => $posting->qualification_training,
                'experience' => $posting->qualification_experience,
                'competency' => $posting->qualification_competency,
            ],
        ];
    }
}