<?php

namespace App\Services\Profile;

use App\Models\Employee;
use App\Models\User;
use App\Repositories\Profile\ProfileRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    public function __construct(
        private readonly ProfileRepository $profiles,
    ) {
    }

    public function status(User $user): array
    {
        $user = $this->profiles->userWithEmployeeInfo($user);

        if ($user->hasRole('SuperAdmin')) {
            return [
                'is_complete'    => true,
                'missing_fields' => [],
                'status'         => 'COMPLETE',
                'user'           => $user,
            ];
        }

        $employee = $user->employee;
        $info     = $employee?->info;

        $missing = [];

        if (! $employee?->first_name) $missing['first_name'] = 'First Name';
        if (! $employee?->last_name)  $missing['last_name']  = 'Last Name';
        if (! $info?->date_of_birth)  $missing['date_of_birth'] = 'Date of Birth';
        if (! $info?->sex_at_birth)   $missing['sex_at_birth']  = 'Sex at Birth';
        if (! $info?->civil_status)   $missing['civil_status']  = 'Civil Status';
        if (! $info?->citizenship)    $missing['citizenship']   = 'Citizenship';
        if (! $info?->mobile_no)      $missing['mobile_no']     = 'Mobile No.';
        if (! $info?->residential_city || ! $info?->residential_province) {
            $missing['residential_address'] = 'Residential Address';
        }

        $rawAvatarPath = $employee?->getRawOriginal('avatar_url');
        $avatarExists  = $rawAvatarPath && file_exists(storage_path('app/public/' . $rawAvatarPath));
        if (! $avatarExists) $missing['avatar'] = 'Profile Photo';

        return [
            'is_complete'    => empty($missing),
            'missing_fields' => $missing,
            'status'         => $info?->status ?? 'EMPTY',
            'user'           => $user,
        ];
    }

    public function rules(): array
    {
        return [
            'avatar' => ['sometimes', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],

            'first_name'      => ['sometimes', 'string', 'max:255'],
            'middle_name'     => ['nullable',  'string', 'max:255'],
            'surname'         => ['sometimes', 'string', 'max:255'],
            'name_extension'  => ['nullable',  'string', 'max:20'],
            'date_of_birth'   => ['required',  'date', 'before:today'],
            'place_of_birth'  => ['nullable',  'string', 'max:255'],
            'sex_at_birth'    => ['required',  'string', 'in:MALE,FEMALE'],
            'civil_status'    => ['required',  'string', 'max:50'],
            'height'          => ['nullable',  'numeric', 'min:0.5', 'max:3'],
            'weight'          => ['nullable',  'numeric', 'min:10',  'max:500'],
            'blood_type'      => ['nullable',  'string', 'max:5'],
            'citizenship'     => ['required',  'string', 'max:100'],

            'umid_id'            => ['nullable', 'string', 'max:50'],
            'pagibig_id'         => ['nullable', 'string', 'max:50'],
            'philhealth_no'      => ['nullable', 'string', 'max:50'],
            'sss_no'             => ['nullable', 'string', 'max:50'],
            'philsys_number'     => ['nullable', 'string', 'max:50'],
            'tin_no'             => ['nullable', 'string', 'max:50'],
            'agency_employee_no' => ['nullable', 'string', 'max:50'],

            'residential_province'    => ['required', 'string', 'max:100'],
            'residential_city'        => ['required', 'string', 'max:100'],
            'residential_barangay'    => ['nullable', 'string', 'max:100'],
            'residential_house_no'    => ['nullable', 'string', 'max:100'],
            'residential_street'      => ['nullable', 'string', 'max:100'],
            'residential_subdivision' => ['nullable', 'string', 'max:100'],
            'residential_zip'         => ['nullable', 'string', 'max:10'],

            'permanent_province'    => ['nullable', 'string', 'max:100'],
            'permanent_city'        => ['nullable', 'string', 'max:100'],
            'permanent_barangay'    => ['nullable', 'string', 'max:100'],
            'permanent_house_no'    => ['nullable', 'string', 'max:100'],
            'permanent_street'      => ['nullable', 'string', 'max:100'],
            'permanent_subdivision' => ['nullable', 'string', 'max:100'],
            'permanent_zip'         => ['nullable', 'string', 'max:10'],

            'telephone_no'  => ['nullable', 'string', 'max:50'],
            'mobile_no'     => ['required', 'string', 'max:50'],
            'email_address' => ['nullable', 'email',  'max:255'],

            'spouse_surname'          => ['nullable', 'string', 'max:100'],
            'spouse_first_name'       => ['nullable', 'string', 'max:100'],
            'spouse_middle_name'      => ['nullable', 'string', 'max:100'],
            'spouse_extension'        => ['nullable', 'string', 'max:20'],
            'spouse_occupation'       => ['nullable', 'string', 'max:255'],
            'spouse_employer'         => ['nullable', 'string', 'max:255'],
            'spouse_business_address' => ['nullable', 'string', 'max:500'],
            'spouse_telephone'        => ['nullable', 'string', 'max:50'],

            'father_surname'     => ['nullable', 'string', 'max:100'],
            'father_first_name'  => ['nullable', 'string', 'max:100'],
            'father_middle_name' => ['nullable', 'string', 'max:100'],
            'father_extension'   => ['nullable', 'string', 'max:20'],

            'mother_maiden_surname' => ['nullable', 'string', 'max:100'],
            'mother_first_name'     => ['nullable', 'string', 'max:100'],
            'mother_middle_name'    => ['nullable', 'string', 'max:100'],

            'children' => ['nullable', 'string'],

            'edu_elementary' => ['nullable', 'string'],
            'edu_secondary'  => ['nullable', 'string'],
            'edu_vocational' => ['nullable', 'string'],
            'edu_college'    => ['nullable', 'string'],
            'edu_graduate'   => ['nullable', 'string'],

            'eligibilities' => ['nullable', 'string'],
            'work_experiences' => ['nullable', 'string'],
            'voluntary_works' => ['nullable', 'string'],
            'trainings' => ['nullable', 'string'],

            'special_skills'            => ['nullable', 'string'],
            'non_academic_distinctions' => ['nullable', 'string'],
            'organization_memberships'  => ['nullable', 'string'],

            'related_3rd_degree'             => ['nullable', 'string', 'in:YES,NO'],
            'related_3rd_degree_details'     => ['nullable', 'string', 'max:500'],
            'related_4th_degree'             => ['nullable', 'string', 'in:YES,NO'],
            'related_4th_degree_details'     => ['nullable', 'string', 'max:500'],
            'found_guilty_admin'             => ['nullable', 'string', 'in:YES,NO'],
            'found_guilty_admin_details'     => ['nullable', 'string', 'max:500'],
            'criminally_charged'             => ['nullable', 'string', 'in:YES,NO'],
            'criminally_charged_details'     => ['nullable', 'string', 'max:500'],
            'convicted_crime'                => ['nullable', 'string', 'in:YES,NO'],
            'convicted_crime_details'        => ['nullable', 'string', 'max:500'],
            'separated_from_service'         => ['nullable', 'string', 'in:YES,NO'],
            'separated_from_service_details' => ['nullable', 'string', 'max:500'],
            'candidate_in_election'          => ['nullable', 'string', 'in:YES,NO'],
            'candidate_in_election_details'  => ['nullable', 'string', 'max:500'],
            'resigned_to_campaign'           => ['nullable', 'string', 'in:YES,NO'],
            'resigned_to_campaign_details'   => ['nullable', 'string', 'max:500'],
            'immigrant_status'               => ['nullable', 'string', 'in:YES,NO'],
            'immigrant_country'              => ['nullable', 'string', 'max:100'],
            'is_indigenous'                  => ['nullable', 'string', 'in:YES,NO'],
            'indigenous_specify'             => ['nullable', 'string', 'max:255'],
            'is_pwd'                         => ['nullable', 'string', 'in:YES,NO'],
            'pwd_id_no'                      => ['nullable', 'string', 'max:100'],
            'is_solo_parent'                 => ['nullable', 'string', 'in:YES,NO'],
            'solo_parent_id_no'              => ['nullable', 'string', 'max:100'],

            'govt_id_type'       => ['nullable', 'string', 'max:50'],
            'govt_id_type_other' => ['nullable', 'string', 'max:100', 'required_if:govt_id_type,OTHER'],
            'govt_id_no'         => ['nullable', 'string', 'max:100'],
            'govt_id_date_place' => ['nullable', 'string', 'max:255'],

            'license_number'       => ['nullable', 'string', 'max:100'],
            'license_date_issued'  => ['nullable', 'date'],
            'license_expiry_date'  => ['nullable', 'date'],
            'license_place_issued' => ['nullable', 'string', 'max:255'],

            'references' => ['nullable', 'string'],
        ];
    }

    public function nullifyBlankDates(Request $request): void
    {
        $dateFields = ['date_of_birth', 'license_date_issued', 'license_expiry_date'];

        foreach ($dateFields as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
    }

    public function complete(Request $request): array
    {
        $this->nullifyBlankDates($request);
        $data = $request->validate($this->rules());

        $user     = $request->user()->fresh()->load('employee.info');
        $employee = $user->employee;

        if (! $employee) {
            return ['status' => 422, 'body' => ['message' => 'No employee record linked to this account.']];
        }

        DB::transaction(function () use ($request, $data, $employee) {
            if ($request->hasFile('avatar')) {
                $this->profiles->storeAvatar($employee, $request->file('avatar'));
            }

            $nameFields = array_filter([
                'first_name'  => $data['first_name']     ?? null,
                'middle_name' => $data['middle_name']    ?? null,
                'last_name'   => $data['surname']        ?? null,
                'suffix'      => $data['name_extension'] ?? null,
            ]);
            $this->profiles->updateEmployeeNameFields($employee, $nameFields);

            $infoFields = $this->extractInfoFields($data);
            $this->profiles->updateInfo($employee, $infoFields);

            $employee->refresh()->load('info');

            $this->syncChildren($employee->id, $data['children'] ?? []);
            $this->syncEducation($employee->id, $data);
            $this->syncSimple($this->profiles->eligibilityModel(), $employee->id, $data['eligibilities'] ?? [], fn ($r) => [
                'eligibility' => $r['eligibility'] ?? null,
                'rating'      => $r['rating']      ?? null,
                'exam_date'   => $this->dateOrNull($r['exam_date']   ?? null),
                'place'       => $r['place']       ?? null,
                'license_no'  => $r['license_no']  ?? null,
                'valid_until' => $this->dateOrNull($r['valid_until'] ?? null),
            ]);
            $this->syncSimple($this->profiles->workExperienceModel(), $employee->id, $data['work_experiences'] ?? [], fn ($r) => [
                'date_from'          => $this->dateOrNull($r['from'] ?? null),
                'date_to'            => $this->dateOrNull($r['to']   ?? null),
                'position'           => $r['position']       ?? null,
                'department'         => $r['department']     ?? null,
                'monthly_salary'     => is_numeric($r['monthly_salary'] ?? null) ? $r['monthly_salary'] : null,
                'salary_grade'       => $r['salary_grade']   ?? null,
                'appointment_status' => $r['status']         ?? null,
                'govt_service'       => $r['govt_service']   ?? null,
            ]);
            $this->syncSimple($this->profiles->voluntaryWorkModel(), $employee->id, $data['voluntary_works'] ?? [], fn ($r) => [
                'org_name_address' => $r['org_name_address'] ?? null,
                'date_from'        => $this->dateOrNull($r['from'] ?? null),
                'date_to'          => $this->dateOrNull($r['to']   ?? null),
                'hours'            => $r['hours']            ?? null,
                'position'         => $r['position']         ?? null,
            ]);
            $this->syncSimple($this->profiles->trainingModel(), $employee->id, $data['trainings'] ?? [], fn ($r) => [
                'title'        => $r['title']        ?? null,
                'date_from'    => $this->dateOrNull($r['from'] ?? null),
                'date_to'      => $this->dateOrNull($r['to']   ?? null),
                'hours'        => $r['hours']        ?? null,
                'type'         => $r['type']         ?? null,
                'sponsored_by' => $r['sponsored_by'] ?? null,
            ]);
            $this->syncSimple($this->profiles->specialSkillModel(), $employee->id, $data['special_skills'] ?? [], fn ($r) => [
                'skill' => $r['skill'] ?? null,
            ]);
            $this->syncSimple($this->profiles->nonAcademicDistinctionModel(), $employee->id, $data['non_academic_distinctions'] ?? [], fn ($r) => [
                'distinction' => $r['distinction'] ?? null,
            ]);
            $this->syncSimple($this->profiles->organizationMembershipModel(), $employee->id, $data['organization_memberships'] ?? [], fn ($r) => [
                'organization' => $r['organization'] ?? null,
            ]);
            $this->syncSimple($this->profiles->referenceModel(), $employee->id, $data['references'] ?? [], fn ($r) => [
                'name'    => $r['name']    ?? null,
                'address' => $r['address'] ?? null,
                'contact' => $r['contact'] ?? null,
            ]);
        });

        $employee->refresh()->load('info');

        return [
            'status' => 200,
            'body' => [
                'message' => 'Profile updated successfully.',
                'status'  => $employee->info?->status ?? 'EMPTY',
            ],
        ];
    }

    private function extractInfoFields(array $data): array
    {
        $scalar = [
            'surname', 'name_extension',
            'date_of_birth', 'place_of_birth', 'sex_at_birth', 'civil_status',
            'height', 'weight', 'blood_type', 'citizenship',
            'umid_id', 'pagibig_id', 'philhealth_no', 'sss_no', 'philsys_number', 'tin_no', 'agency_employee_no',
            'residential_province', 'residential_city', 'residential_barangay',
            'residential_house_no', 'residential_street', 'residential_subdivision', 'residential_zip',
            'permanent_province', 'permanent_city', 'permanent_barangay',
            'permanent_house_no', 'permanent_street', 'permanent_subdivision', 'permanent_zip',
            'telephone_no', 'mobile_no', 'email_address',
            'spouse_surname', 'spouse_first_name', 'spouse_middle_name', 'spouse_extension',
            'spouse_occupation', 'spouse_employer', 'spouse_business_address', 'spouse_telephone',
            'father_surname', 'father_first_name', 'father_middle_name', 'father_extension',
            'mother_maiden_surname', 'mother_first_name', 'mother_middle_name',
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
            'govt_id_type', 'govt_id_type_other', 'govt_id_no', 'govt_id_date_place',
            'license_number', 'license_date_issued', 'license_expiry_date', 'license_place_issued',
        ];

        return collect($scalar)
            ->filter(fn ($key) => array_key_exists($key, $data))
            ->mapWithKeys(fn ($key) => [$key => $data[$key]])
            ->toArray();
    }

    private function decodeJson(?string $value): array
    {
        if (! $value) return [];
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value)) return null;
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function syncSimple(string $modelClass, int $employeeId, string|array $raw, callable $map): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->profiles->syncRows($modelClass, $employeeId, $rows, $map);
    }

    private function syncChildren(int $employeeId, string|array $raw): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->profiles->syncRows($this->profiles->childModel(), $employeeId, $rows, fn ($r) => [
            'name'          => $r['name']          ?? null,
            'date_of_birth' => $this->dateOrNull($r['date_of_birth'] ?? null),
        ]);
    }

    private function syncEducation(int $employeeId, array $data): void
    {
        $levels = ['elementary', 'secondary', 'vocational', 'college', 'graduate'];
        $rowsByLevel = [];

        foreach ($levels as $level) {
            $rowsByLevel[$level] = $this->decodeJson($data["edu_{$level}"] ?? null);
        }

        $this->profiles->syncEducation($employeeId, $rowsByLevel);
    }
}
