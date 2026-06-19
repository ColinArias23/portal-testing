<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeInfo;
use App\Models\PdsChild;
use App\Models\PdsEducation;
use App\Models\PdsEligibility;
use App\Models\PdsWorkExperience;
use App\Models\PdsVoluntaryWork;
use App\Models\PdsTraining;
use App\Models\PdsSpecialSkill;
use App\Models\PdsNonAcademicDistinction;
use App\Models\PdsOrganizationMembership;
use App\Models\PdsReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdsProfileController extends Controller
{
    // ── Scalar fields that live on employee_info ──────────────────────────────
    private const SCALAR_FIELDS = [
        // Personal
        'surname', 'name_extension', 'date_of_birth', 'place_of_birth',
        'sex_at_birth', 'civil_status', 'height', 'weight', 'blood_type', 'citizenship',
        // Government IDs
        'umid_id', 'pagibig_id', 'philhealth_no', 'sss_no', 'philsys_number',
        'tin_no', 'agency_employee_no',
        // Residential address
        'residential_province', 'residential_city', 'residential_barangay',
        'residential_house_no', 'residential_street', 'residential_subdivision', 'residential_zip',
        // Permanent address
        'permanent_province', 'permanent_city', 'permanent_barangay',
        'permanent_house_no', 'permanent_street', 'permanent_subdivision', 'permanent_zip',
        // Contact
        'telephone_no', 'mobile_no', 'email_address',
        // Family
        'spouse_surname', 'spouse_first_name', 'spouse_middle_name', 'spouse_extension',
        'spouse_occupation', 'spouse_employer', 'spouse_business_address', 'spouse_telephone',
        'father_surname', 'father_first_name', 'father_middle_name', 'father_extension',
        'mother_maiden_surname', 'mother_first_name', 'mother_middle_name',
        // Questions
        'related_3rd_degree', 'related_3rd_degree_details',
        'related_4th_degree', 'related_4th_degree_details',
        'found_guilty_admin', 'found_guilty_admin_details',
        'criminally_charged', 'criminally_charged_details',
        'convicted_crime', 'convicted_crime_details',
        'separated_from_service', 'separated_from_service_details',
        'candidate_in_election', 'candidate_in_election_details',
        'resigned_to_campaign', 'resigned_to_campaign_details',
        'immigrant_status', 'immigrant_country',
        'is_indigenous', 'indigenous_specify',
        'is_pwd', 'pwd_id_no',
        'is_solo_parent', 'solo_parent_id_no',
        // Gov't ID
        'govt_id_type', 'govt_id_type_other', 'govt_id_no', 'govt_id_date_place',
        // License
        'license_number', 'license_date_issued', 'license_expiry_date', 'license_place_issued',
    ];

    // Fields whose case must be preserved (not uppercased)
    private const PRESERVE_CASE = [
        'email_address',
        'date_of_birth',
        'license_date_issued',
        'license_expiry_date',
        'govt_id_date_place',
    ];


    public function status(Request $request)
    {
        /** @var \App\Models\User $user */
        $user     = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $info = $employee->info;

        // No employee_info row yet at all → definitely incomplete/empty.
        if (! $info) {
            return response()->json([
                'is_complete'    => false,
                'missing_fields' => [],
            ]);
        }

        $isComplete = $info->status === 'COMPLETE';

        return response()->json([
            'is_complete'    => $isComplete,
            'missing_fields' => $isComplete ? [] : $this->resolveMissingFields($info, $employee),
        ]);
    }
    
    private function resolveMissingFields(EmployeeInfo $info, Employee $employee): array
    {
        $requiredFields = [
            'date_of_birth', 'sex_at_birth', 'civil_status', 'citizenship',
            'residential_city', 'residential_province', 'mobile_no',
        ];

        $missing = [];
        foreach ($requiredFields as $field) {
            if (empty($info->$field)) {
                $missing[] = $field;
            }
        }

        if (empty($employee->first_name) || empty($employee->last_name)) {
            $missing[] = 'name';
        }

        $avatarPath = $employee->getRawOriginal('avatar_url');
        if (! $avatarPath || ! file_exists(storage_path('app/public/' . $avatarPath))) {
            $missing[] = 'avatar';
        }

        return $missing;
    }

    // ───────────────────────────── COMPLETE / UPDATE PROFILE ────────────────────────────────────────────

    public function complete(Request $request)
    {
        /** @var \App\Models\User $user */
        $user     = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $validated = $request->validate($this->validationRules());

        DB::transaction(function () use ($request, $employee) {

            // ── 1. Scalar fields → employee_info ─────────────────────────────
            $scalarPayload = $this->buildScalarPayload($request);

            $info = $employee->info()->updateOrCreate(
                ['employee_id' => $employee->id],
                $scalarPayload,
            );

            // ── 2. Avatar ─────────────────────────────────────────────────────
            if ($request->hasFile('avatar')) {
                $oldPath = $employee->getRawOriginal('avatar_url');
                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }
                $path = 'avatars/' . Str::uuid() . '.' . $request->file('avatar')->getClientOriginalExtension();
                Storage::disk('public')->put($path, file_get_contents($request->file('avatar')->getRealPath()));
                $employee->update(['avatar_url' => $path]);

                $info->save();
            }

            // ── 3. Array / relational fields ──────────────────────────────────
            $this->syncRelational($employee, $request);
        });

        return response()->json(['message' => 'Profile completed successfully.']);
    }

    // ──────────────────────────── VALIDATION RULES ─────────────────────────────────────────────

    private function validationRules(): array
    {
        return [
            // ── Required scalars (mirrors EmployeeInfo::REQUIRED_FIELDS) ──────
            'date_of_birth'        => ['required', 'date', 'before:today'],
            'sex_at_birth'         => ['required', 'string', 'in:MALE,FEMALE'],
            'civil_status'         => ['required', 'string', 'max:30'],
            'citizenship'          => ['required', 'string', 'max:60'],
            'residential_city'     => ['required', 'string', 'max:100'],
            'residential_province' => ['required', 'string', 'max:100'],
            'mobile_no'            => ['required', 'string', 'max:30'],

            // ── Optional scalars ────────────────────────────────────────────
            'surname'                  => ['nullable', 'string', 'max:100'],
            'name_extension'           => ['nullable', 'string', 'max:20'],
            'place_of_birth'           => ['nullable', 'string', 'max:255'],
            'height'                   => ['nullable', 'numeric', 'between:0,3'],
            'weight'                   => ['nullable', 'numeric', 'between:0,500'],
            'blood_type'               => ['nullable', 'string', 'max:5'],
            'umid_id'                  => ['nullable', 'string', 'max:30'],
            'pagibig_id'               => ['nullable', 'string', 'max:30'],
            'philhealth_no'            => ['nullable', 'string', 'max:30'],
            'sss_no'                   => ['nullable', 'string', 'max:30'],
            'philsys_number'           => ['nullable', 'string', 'max:30'],
            'tin_no'                   => ['nullable', 'string', 'max:30'],
            'agency_employee_no'       => ['nullable', 'string', 'max:50'],
            'residential_barangay'     => ['nullable', 'string', 'max:100'],
            'residential_house_no'     => ['nullable', 'string', 'max:50'],
            'residential_street'       => ['nullable', 'string', 'max:100'],
            'residential_subdivision'  => ['nullable', 'string', 'max:100'],
            'residential_zip'          => ['nullable', 'string', 'max:10'],
            'permanent_province'       => ['nullable', 'string', 'max:100'],
            'permanent_city'           => ['nullable', 'string', 'max:100'],
            'permanent_barangay'       => ['nullable', 'string', 'max:100'],
            'permanent_house_no'       => ['nullable', 'string', 'max:50'],
            'permanent_street'         => ['nullable', 'string', 'max:100'],
            'permanent_subdivision'    => ['nullable', 'string', 'max:100'],
            'permanent_zip'            => ['nullable', 'string', 'max:10'],
            'telephone_no'             => ['nullable', 'string', 'max:30'],
            'email_address'            => ['nullable', 'email', 'max:100'],

            'spouse_surname'           => ['nullable', 'string', 'max:100'],
            'spouse_first_name'       => ['nullable', 'string', 'max:100'],
            'spouse_middle_name'       => ['nullable', 'string', 'max:100'],
            'spouse_extension'         => ['nullable', 'string', 'max:20'],
            'spouse_occupation'        => ['nullable', 'string', 'max:100'],
            'spouse_employer'          => ['nullable', 'string', 'max:100'],
            'spouse_business_address'  => ['nullable', 'string'],
            'spouse_telephone'         => ['nullable', 'string', 'max:30'],
            'father_surname'           => ['nullable', 'string', 'max:100'],
            'father_first_name'        => ['nullable', 'string', 'max:100'],
            'father_middle_name'       => ['nullable', 'string', 'max:100'],
            'father_extension'         => ['nullable', 'string', 'max:20'],
            'mother_maiden_surname'    => ['nullable', 'string', 'max:100'],
            'mother_first_name'        => ['nullable', 'string', 'max:100'],
            'mother_middle_name'       => ['nullable', 'string', 'max:100'],

            'related_3rd_degree'              => ['nullable', 'string', 'in:YES,NO'],
            'related_3rd_degree_details'      => ['nullable', 'string'],
            'related_4th_degree'              => ['nullable', 'string', 'in:YES,NO'],
            'related_4th_degree_details'      => ['nullable', 'string'],
            'found_guilty_admin'              => ['nullable', 'string', 'in:YES,NO'],
            'found_guilty_admin_details'      => ['nullable', 'string'],
            'criminally_charged'              => ['nullable', 'string', 'in:YES,NO'],
            'criminally_charged_details'      => ['nullable', 'string'],
            'convicted_crime'                 => ['nullable', 'string', 'in:YES,NO'],
            'convicted_crime_details'         => ['nullable', 'string'],
            'separated_from_service'          => ['nullable', 'string', 'in:YES,NO'],
            'separated_from_service_details'  => ['nullable', 'string'],
            'candidate_in_election'           => ['nullable', 'string', 'in:YES,NO'],
            'candidate_in_election_details'   => ['nullable', 'string'],
            'resigned_to_campaign'            => ['nullable', 'string', 'in:YES,NO'],
            'resigned_to_campaign_details'    => ['nullable', 'string'],
            'immigrant_status'                => ['nullable', 'string', 'in:YES,NO'],
            'immigrant_country'               => ['nullable', 'string', 'max:100'],
            'is_indigenous'                   => ['nullable', 'string', 'in:YES,NO'],
            'indigenous_specify'              => ['nullable', 'string', 'max:100'],
            'is_pwd'                          => ['nullable', 'string', 'in:YES,NO'],
            'pwd_id_no'                       => ['nullable', 'string', 'max:50'],
            'is_solo_parent'                  => ['nullable', 'string', 'in:YES,NO'],
            'solo_parent_id_no'               => ['nullable', 'string', 'max:50'],

            'govt_id_type'         => ['nullable', 'string', 'max:60'],
            'govt_id_type_other'   => ['nullable', 'string', 'max:100', 'required_if:govt_id_type,OTHER'],
            'govt_id_no'           => ['nullable', 'string', 'max:60'],
            'govt_id_date_place'   => ['nullable', 'string', 'max:100'],

            'license_number'        => ['nullable', 'string', 'max:60'],
            'license_date_issued'   => ['nullable', 'date'],
            'license_expiry_date'   => ['nullable', 'date', 'after_or_equal:license_date_issued'],
            'license_place_issued'  => ['nullable', 'string', 'max:100'],

            // ── Avatar ───────────────────────────────────────────────────────
            'avatar' => ['nullable', 'image', 'max:10240'], 

            'children'                  => ['nullable', 'string'],
            'edu_elementary'            => ['nullable', 'string'],
            'edu_secondary'             => ['nullable', 'string'],
            'edu_vocational'            => ['nullable', 'string'],
            'edu_college'               => ['nullable', 'string'],
            'edu_graduate'              => ['nullable', 'string'],
            'eligibilities'             => ['nullable', 'string'],
            'work_experiences'          => ['nullable', 'string'],
            'voluntary_works'           => ['nullable', 'string'],
            'trainings'                 => ['nullable', 'string'],
            'special_skills'            => ['nullable', 'string'],
            'non_academic_distinctions' => ['nullable', 'string'],
            'organization_memberships'  => ['nullable', 'string'],
            'references'                => ['nullable', 'string'],
        ];
    }

    private function buildScalarPayload(Request $request): array
    {
        $payload = [];

        foreach (self::SCALAR_FIELDS as $field) {
            if (! $request->has($field)) continue;

            $value = $request->input($field);

            if (! is_string($value) || $value === '') {
                $payload[$field] = $value;
                continue;
            }

            $trimmed = trim($value);

            if ($field === 'email_address') {
                $payload[$field] = strtolower($trimmed);
            } elseif (in_array($field, self::PRESERVE_CASE)) {
                $payload[$field] = $trimmed;
            } else {
                $payload[$field] = strtoupper($trimmed);
            }
        }

        return $payload;
    }

    private function syncRelational(Employee $employee, Request $request): void
    {
        $empId = $employee->id;

        // ── Children ──────────────────────────────────────────────────────────
        PdsChild::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'children') as $i => $row) {
            PdsChild::create([
                'employee_id'   => $empId,
                'name'          => $this->up($row['name'] ?? ''),
                'date_of_birth' => $this->dateOrNull($row['date_of_birth'] ?? null),
                'order'         => $i,
            ]);
        }

        // ── Education ─────────────────────────────────────────────────────────
        PdsEducation::where('employee_id', $empId)->delete();
        $eduLevels = ['elementary', 'secondary', 'vocational', 'college', 'graduate'];
        foreach ($eduLevels as $level) {
            foreach ($this->decodeJson($request, "edu_{$level}") as $i => $row) {
                PdsEducation::create([
                    'employee_id'    => $empId,
                    'level'          => $level,
                    'school'         => $this->up($row['school']         ?? ''),
                    'degree'         => $this->up($row['degree']         ?? ''),
                    'period_from'    => $this->up($row['period_from']    ?? ''),
                    'period_to'      => $this->up($row['period_to']      ?? ''),
                    'highest_level'  => $this->up($row['highest_level']  ?? ''),
                    'year_graduated' => $this->up($row['year_graduated'] ?? ''),
                    'honors'         => $this->up($row['honors']         ?? ''),
                    'order'          => $i,
                ]);
            }
        }

        // ── Eligibilities ─────────────────────────────────────────────────────
        PdsEligibility::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'eligibilities') as $i => $row) {
            PdsEligibility::create([
                'employee_id' => $empId,
                'eligibility' => $this->up($row['eligibility'] ?? ''),
                'rating'      => $this->up($row['rating']      ?? ''),
                'exam_date'   => $this->dateOrNull($row['exam_date']   ?? null),
                'place'       => $this->up($row['place']       ?? ''),
                'license_no'  => $this->up($row['license_no']  ?? ''),
                'valid_until' => $this->dateOrNull($row['valid_until'] ?? null),
                'order'       => $i,
            ]);
        }

        // ── Work Experience ───────────────────────────────────────────────────
        PdsWorkExperience::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'work_experiences') as $i => $row) {
            $salary = $row['monthly_salary'] ?? null;
            PdsWorkExperience::create([
                'employee_id'        => $empId,
                'date_from'          => $this->dateOrNull($row['from'] ?? null),
                'date_to'            => $this->dateOrNull($row['to']   ?? null),
                'position'           => $this->up($row['position']       ?? ''),
                'department'         => $this->up($row['department']     ?? ''),
                'monthly_salary'     => ($salary === null || $salary === '') ? null : (float) $salary,
                'salary_grade'       => $this->up($row['salary_grade']   ?? ''),
                'appointment_status' => $this->up($row['status']         ?? ''),
                'govt_service'       => $this->up($row['govt_service']   ?? ''),
                'order'              => $i,
            ]);
        }

        // ── Voluntary Work ────────────────────────────────────────────────────
        PdsVoluntaryWork::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'voluntary_works') as $i => $row) {
            PdsVoluntaryWork::create([
                'employee_id'      => $empId,
                'org_name_address' => $this->up($row['org_name_address'] ?? ''),
                'date_from'        => $this->dateOrNull($row['from'] ?? null),
                'date_to'          => $this->dateOrNull($row['to']   ?? null),
                'hours'            => $this->up($row['hours']    ?? ''),
                'position'         => $this->up($row['position'] ?? ''),
                'order'            => $i,
            ]);
        }

        // ── Trainings ─────────────────────────────────────────────────────────
        PdsTraining::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'trainings') as $i => $row) {
            PdsTraining::create([
                'employee_id'  => $empId,
                'title'        => $this->up($row['title']        ?? ''),
                'date_from'    => $this->dateOrNull($row['from'] ?? null),
                'date_to'      => $this->dateOrNull($row['to']   ?? null),
                'hours'        => $this->up($row['hours']        ?? ''),
                'type'         => $this->up($row['type']         ?? ''),
                'sponsored_by' => $this->up($row['sponsored_by'] ?? ''),
                'order'        => $i,
            ]);
        }

        // ── Special Skills ────────────────────────────────────────────────────
        PdsSpecialSkill::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'special_skills') as $i => $row) {
            PdsSpecialSkill::create([
                'employee_id' => $empId,
                'skill'       => $this->up($row['skill'] ?? ''),
                'order'       => $i,
            ]);
        }

        // ── Non-Academic Distinctions ─────────────────────────────────────────
        PdsNonAcademicDistinction::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'non_academic_distinctions') as $i => $row) {
            PdsNonAcademicDistinction::create([
                'employee_id' => $empId,
                'distinction' => $this->up($row['distinction'] ?? ''),
                'order'       => $i,
            ]);
        }

        // ── Organization Memberships ──────────────────────────────────────────
        PdsOrganizationMembership::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'organization_memberships') as $i => $row) {
            PdsOrganizationMembership::create([
                'employee_id'  => $empId,
                'organization' => $this->up($row['organization'] ?? ''),
                'order'        => $i,
            ]);
        }

        // ── References ────────────────────────────────────────────────────────
        PdsReference::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'references') as $i => $row) {
            PdsReference::create([
                'employee_id' => $empId,
                'name'        => $this->up($row['name']    ?? ''),
                'address'     => $this->up($row['address'] ?? ''),
                'contact'     => $this->up($row['contact'] ?? ''),
                'order'       => $i,
            ]);
        }
    }

    // ──────────────────────────────────PRIVATE HELPERS─────────────────────────────────────── 

    private function decodeJson(Request $request, string $field): array
    {
        $raw = $request->input($field);
        if (! $raw) return [];
        if (is_array($raw)) return $raw;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function up(mixed $value): string
    {
        if (! is_string($value)) return '';
        $trimmed = trim($value);
        return $trimmed !== '' ? strtoupper($trimmed) : '';
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value)) return null;
        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }
}