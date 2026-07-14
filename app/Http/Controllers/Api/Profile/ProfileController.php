<?php

namespace App\Http\Controllers\Api\Profile;

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

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
// ── Status ────────────────────────────────────────────────────────────────
    public function status(Request $request)
    {
        $user = $request->user()->load('employee.info');

        if ($user->hasRole('SuperAdmin')) {
            return response()->json([
                'is_complete'    => true,
                'missing_fields' => [],
                'status'         => 'COMPLETE',
                'user'           => $user,
            ]);
        }

        $employee = $user->employee;
        $info     = $employee?->info;

        $missing = [];

        if (!$employee?->first_name) $missing['first_name'] = 'First Name';
        if (!$employee?->last_name)  $missing['last_name']  = 'Last Name';
        if (!$info?->date_of_birth)  $missing['date_of_birth'] = 'Date of Birth';
        if (!$info?->sex_at_birth)   $missing['sex_at_birth']  = 'Sex at Birth';
        if (!$info?->civil_status)   $missing['civil_status']  = 'Civil Status';
        if (!$info?->citizenship)    $missing['citizenship']   = 'Citizenship';
        if (!$info?->mobile_no)      $missing['mobile_no']     = 'Mobile No.';
        if (!$info?->residential_city || !$info?->residential_province) {
            $missing['residential_address'] = 'Residential Address';
        }

        $rawAvatarPath = $employee?->getRawOriginal('avatar_url');
        $avatarExists  = $rawAvatarPath && file_exists(storage_path('app/public/' . $rawAvatarPath));
        if (!$avatarExists) $missing['avatar'] = 'Profile Photo';

        return response()->json([
            'is_complete'    => empty($missing),
            'missing_fields' => $missing,
            'status'         => $info?->status ?? 'EMPTY',
            'user'           => $user,
        ]);
    }
// ── Complete ──────────────────────────────────────────────────────────────
    public function complete(Request $request)
    {
        $this->nullifyBlankDates($request);

        $data = $request->validate($this->rules());

        $user     = $request->user()->fresh()->load('employee.info');
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'No employee record linked to this account.'], 422);
        }

        DB::transaction(function () use ($request, $data, $employee) {

            // ── Avatar ────────────────────────────────────────────────────
            if ($request->hasFile('avatar')) {
                $oldPath = $employee->getRawOriginal('avatar_url');
                if ($oldPath && file_exists(storage_path('app/public/' . $oldPath))) {
                    Storage::disk('public')->delete($oldPath);
                }
                $file = $request->file('avatar');
                $path = 'avatars/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
                Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));
                $employee->update(['avatar_url' => $path]);
            }

            // ── Sync name back to employees table ─────────────────────────
            $nameFields = array_filter([
                'first_name'  => $data['first_name']     ?? null,
                'middle_name' => $data['middle_name']    ?? null,
                'last_name'   => $data['surname']        ?? null,
                'suffix'      => $data['name_extension'] ?? null,
            ]);
            if ($nameFields) {
                $employee->update($nameFields);
            }

            // ── Scalar PDS fields → employee_info ─────────────────────────
            $infoFields = $this->extractInfoFields($data);
            $employee->info()->updateOrCreate(
                ['employee_id' => $employee->id],
                $infoFields
            );

            $employee->refresh()->load('info');

            // ── Relational / table fields ─────────────────────────────────
            $this->syncChildren($employee->id,                $data['children']                  ?? []);
            $this->syncEducation($employee->id,               $data);
            $this->syncEligibilities($employee->id,           $data['eligibilities']             ?? []);
            $this->syncWorkExperiences($employee->id,         $data['work_experiences']          ?? []);
            $this->syncVoluntaryWorks($employee->id,          $data['voluntary_works']           ?? []);
            $this->syncTrainings($employee->id,               $data['trainings']                 ?? []);
            $this->syncSpecialSkills($employee->id,           $data['special_skills']            ?? []);
            $this->syncNonAcademicDistinctions($employee->id, $data['non_academic_distinctions'] ?? []);
            $this->syncOrganizationMemberships($employee->id, $data['organization_memberships']  ?? []);
            $this->syncReferences($employee->id,              $data['references']                ?? []);
        });

        $employee->refresh()->load('info');

        return response()->json([
            'message' => 'Profile updated successfully.',
            'status'  => $employee->info?->status ?? 'EMPTY',
        ]);
    }
// ── Validationrules ──────────────────────────────────────────────────────
    private function nullifyBlankDates(Request $request): void
    {
        $dateFields = [
            'date_of_birth', 'license_date_issued', 'license_expiry_date',
        ];

        foreach ($dateFields as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
    }

    private function rules(): array
    {
        return [
            // Avatar
            'avatar' => ['sometimes', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],

            // I. Personal
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

            // Government IDs
            'umid_id'            => ['nullable', 'string', 'max:50'],
            'pagibig_id'         => ['nullable', 'string', 'max:50'],
            'philhealth_no'      => ['nullable', 'string', 'max:50'],
            'sss_no'             => ['nullable', 'string', 'max:50'],
            'philsys_number'     => ['nullable', 'string', 'max:50'],
            'tin_no'             => ['nullable', 'string', 'max:50'],
            'agency_employee_no' => ['nullable', 'string', 'max:50'],

            // Residential address — required (mirrors EmployeeInfo::REQUIRED_FIELDS)
            'residential_province'    => ['required', 'string', 'max:100'],
            'residential_city'        => ['required', 'string', 'max:100'],
            'residential_barangay'    => ['nullable', 'string', 'max:100'],
            'residential_house_no'    => ['nullable', 'string', 'max:100'],
            'residential_street'      => ['nullable', 'string', 'max:100'],
            'residential_subdivision' => ['nullable', 'string', 'max:100'],
            'residential_zip'         => ['nullable', 'string', 'max:10'],

            // Permanent address
            'permanent_province'    => ['nullable', 'string', 'max:100'],
            'permanent_city'        => ['nullable', 'string', 'max:100'],
            'permanent_barangay'    => ['nullable', 'string', 'max:100'],
            'permanent_house_no'    => ['nullable', 'string', 'max:100'],
            'permanent_street'      => ['nullable', 'string', 'max:100'],
            'permanent_subdivision' => ['nullable', 'string', 'max:100'],
            'permanent_zip'         => ['nullable', 'string', 'max:10'],

            // Contact
            'telephone_no'  => ['nullable', 'string', 'max:50'],
            'mobile_no'     => ['required', 'string', 'max:50'],
            'email_address' => ['nullable', 'email',  'max:255'],

            // II. Family — spouse
            'spouse_surname'          => ['nullable', 'string', 'max:100'],
            'spouse_first_name'       => ['nullable', 'string', 'max:100'],
            'spouse_middle_name'      => ['nullable', 'string', 'max:100'],
            'spouse_extension'        => ['nullable', 'string', 'max:20'],
            'spouse_occupation'       => ['nullable', 'string', 'max:255'],
            'spouse_employer'         => ['nullable', 'string', 'max:255'],
            'spouse_business_address' => ['nullable', 'string', 'max:500'],
            'spouse_telephone'        => ['nullable', 'string', 'max:50'],

            // II. Family — father
            'father_surname'     => ['nullable', 'string', 'max:100'],
            'father_first_name'  => ['nullable', 'string', 'max:100'],
            'father_middle_name' => ['nullable', 'string', 'max:100'],
            'father_extension'   => ['nullable', 'string', 'max:20'],

            // II. Family — mother
            'mother_maiden_surname' => ['nullable', 'string', 'max:100'],
            'mother_first_name'     => ['nullable', 'string', 'max:100'],
            'mother_middle_name'    => ['nullable', 'string', 'max:100'],

            // II. Children (No. 23)
            'children' => ['nullable', 'string'],

            // III. Education (No. 26)
            'edu_elementary' => ['nullable', 'string'],
            'edu_secondary'  => ['nullable', 'string'],
            'edu_vocational' => ['nullable', 'string'],
            'edu_college'    => ['nullable', 'string'],
            'edu_graduate'   => ['nullable', 'string'],

            // IV. Eligibilities (No. 27)
            'eligibilities' => ['nullable', 'string'],

            // V. Work Experience (No. 28)
            'work_experiences' => ['nullable', 'string'],

            // VI. Voluntary Work (No. 29)
            'voluntary_works' => ['nullable', 'string'],

            // VII. Trainings (No. 30)
            'trainings' => ['nullable', 'string'],

            // VIII. Other
            'special_skills'            => ['nullable', 'string'],
            'non_academic_distinctions' => ['nullable', 'string'],
            'organization_memberships'  => ['nullable', 'string'],

            // IX. Questions (No. 34–40)
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

            // Government-issued ID
            'govt_id_type'       => ['nullable', 'string', 'max:50'],
            'govt_id_type_other' => ['nullable', 'string', 'max:100', 'required_if:govt_id_type,OTHER'],
            'govt_id_no'         => ['nullable', 'string', 'max:100'],
            'govt_id_date_place' => ['nullable', 'string', 'max:255'],

            // Professional License
            'license_number'       => ['nullable', 'string', 'max:100'],
            'license_date_issued'  => ['nullable', 'date'],
            'license_expiry_date'  => ['nullable', 'date'],
            'license_place_issued' => ['nullable', 'string', 'max:255'],

            // 41. References
            'references' => ['nullable', 'string'],
        ];
    }
// ── ExtractInfoFields ─────────────────────
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
            // Professional License
            'license_number', 'license_date_issued', 'license_expiry_date', 'license_place_issued',
        ];
        return collect($scalar)
            ->filter(fn($key) => array_key_exists($key, $data))
            ->mapWithKeys(fn($key) => [$key => $data[$key]])
            ->toArray();
    }
// ── SyncRows ───────────────────────────────────────────────
    private function syncRows(string $model, int $employeeId, array $rows, callable $map): void
    {
        $model::where('employee_id', $employeeId)->delete();

        foreach ($rows as $i => $row) {
            $model::create(array_merge(
                ['employee_id' => $employeeId, 'order' => $i],
                $map($row)
            ));
        }
    }

    private function decodeJson(?string $value): array
    {
        if (!$value) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value)) return null;
        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function syncChildren(int $employeeId, string|array $raw): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->syncRows(PdsChild::class, $employeeId, $rows, fn($r) => [
            'name'          => $r['name']          ?? null,
            'date_of_birth' => $this->dateOrNull($r['date_of_birth'] ?? null),
        ]);
    }
// ─── SyncEducation ─────────────────────────────────────────────────────
    private function syncEducation(int $employeeId, array $data): void
    {
        PdsEducation::where('employee_id', $employeeId)->delete();

        $levels = ['elementary', 'secondary', 'vocational', 'college', 'graduate'];
        foreach ($levels as $level) {
            $rows = $this->decodeJson($data["edu_{$level}"] ?? null);
            foreach ($rows as $i => $row) {
                PdsEducation::create([
                    'employee_id'    => $employeeId,
                    'level'          => $level,
                    'school'         => $row['school']         ?? null,
                    'degree'         => $row['degree']         ?? null,
                    'period_from'    => $row['period_from']    ?? null,
                    'period_to'      => $row['period_to']      ?? null,
                    'highest_level'  => $row['highest_level']  ?? null,
                    'year_graduated' => $row['year_graduated'] ?? null,
                    'honors'         => $row['honors']         ?? null,
                    'order'          => $i,
                ]);
            }
        }
    }
// ─── SyncEligibilities ─────────────────────────────────────────────────────
    private function syncEligibilities(int $employeeId, string|array $raw): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->syncRows(PdsEligibility::class, $employeeId, $rows, fn($r) => [
            'eligibility' => $r['eligibility'] ?? null,
            'rating'      => $r['rating']      ?? null,
            'exam_date'   => $this->dateOrNull($r['exam_date']   ?? null),
            'place'       => $r['place']       ?? null,
            'license_no'  => $r['license_no']  ?? null,
            'valid_until' => $this->dateOrNull($r['valid_until'] ?? null),
        ]);
    }
// ─── SyncWordExperiences ─────────────────────────────────────────────────────
    private function syncWorkExperiences(int $employeeId, string|array $raw): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->syncRows(PdsWorkExperience::class, $employeeId, $rows, fn($r) => [
            'date_from'          => $this->dateOrNull($r['from'] ?? null),
            'date_to'            => $this->dateOrNull($r['to']   ?? null),
            'position'           => $r['position']       ?? null,
            'department'         => $r['department']     ?? null,
            'monthly_salary'     => is_numeric($r['monthly_salary'] ?? null) ? $r['monthly_salary'] : null,
            'salary_grade'       => $r['salary_grade']   ?? null,
            'appointment_status' => $r['status']         ?? null,
            'govt_service'       => $r['govt_service']   ?? null,
        ]);
    }
// ─── SyncVoluntaryWorks ─────────────────────────────────────────────────────
    private function syncVoluntaryWorks(int $employeeId, string|array $raw): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->syncRows(PdsVoluntaryWork::class, $employeeId, $rows, fn($r) => [
            'org_name_address' => $r['org_name_address'] ?? null,
            'date_from'        => $this->dateOrNull($r['from'] ?? null),
            'date_to'          => $this->dateOrNull($r['to']   ?? null),
            'hours'            => $r['hours']            ?? null,
            'position'         => $r['position']         ?? null,
        ]);
    }
// ─── SyncTrainings ─────────────────────────────────────────────────────
    private function syncTrainings(int $employeeId, string|array $raw): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->syncRows(PdsTraining::class, $employeeId, $rows, fn($r) => [
            'title'        => $r['title']        ?? null,
            'date_from'    => $this->dateOrNull($r['from'] ?? null),
            'date_to'      => $this->dateOrNull($r['to']   ?? null),
            'hours'        => $r['hours']        ?? null,
            'type'         => $r['type']         ?? null,
            'sponsored_by' => $r['sponsored_by'] ?? null,
        ]);
    }
// ─── SyncSpecialSkills ─────────────────────────────────────────────────────
    private function syncSpecialSkills(int $employeeId, string|array $raw): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->syncRows(PdsSpecialSkill::class, $employeeId, $rows, fn($r) => [
            'skill' => $r['skill'] ?? null,
        ]);
    }
// ─── SyncNonAcademicDistinctions ─────────────────────────────────────────────────────
    private function syncNonAcademicDistinctions(int $employeeId, string|array $raw): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->syncRows(PdsNonAcademicDistinction::class, $employeeId, $rows, fn($r) => [
            'distinction' => $r['distinction'] ?? null,
        ]);
    }
// ─── SyncOrganizationMemberships ─────────────────────────────────────────────────────
    private function syncOrganizationMemberships(int $employeeId, string|array $raw): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->syncRows(PdsOrganizationMembership::class, $employeeId, $rows, fn($r) => [
            'organization' => $r['organization'] ?? null,
        ]);
    }
// ─── SyncReferences ─────────────────────────────────────────────────────
    private function syncReferences(int $employeeId, string|array $raw): void
    {
        $rows = is_string($raw) ? $this->decodeJson($raw) : $raw;
        $this->syncRows(PdsReference::class, $employeeId, $rows, fn($r) => [
            'name'    => $r['name']    ?? null,
            'address' => $r['address'] ?? null,
            'contact' => $r['contact'] ?? null,
        ]);
    }
}