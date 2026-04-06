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

class CompleteProfileController extends Controller
{
    // ── Scalar fields that live on employee_info ──────────────────────────────
    private const SCALAR_FIELDS = [
        // Personal
        'surname', 'name_extension', 'date_of_birth', 'place_of_birth',
        'sex_at_birth', 'civil_status', 'height', 'weight', 'blood_type', 'citizenship',
        // Government IDs
        'umid_id', 'pagibig_id', 'philhealth_no', 'philsys_number', 'tin_no', 'agency_employee_no',
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
        'govt_id_type', 'govt_id_no', 'govt_id_date_place',
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

    // ─────────────────────────────────────────────────────────────────────────
    // COMPLETE / UPDATE PROFILE
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user     = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        DB::transaction(function () use ($request, $employee) {

            // ── 1. Scalar fields → employee_info ─────────────────────────────
            $scalarPayload = $this->buildScalarPayload($request);

            $info = $employee->info()->updateOrCreate(
                ['employee_id' => $employee->id],
                $scalarPayload,
            );

            // Keep legacy mirror fields in sync
            $info->setRelation('employee', $employee);
            $info->save();

            // ── 2. Avatar ─────────────────────────────────────────────────────
            if ($request->hasFile('avatar')) {
                $oldPath = $employee->getRawOriginal('avatar_url');
                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }
                $path = 'avatars/' . Str::uuid() . '.' . $request->file('avatar')->getClientOriginalExtension();
                Storage::disk('public')->put($path, file_get_contents($request->file('avatar')->getRealPath()));
                $employee->update(['avatar_url' => $path]);
            }

            // ── 3. Array / relational fields ──────────────────────────────────
            $this->syncRelational($employee, $request);
        });

        return response()->json(['message' => 'Profile completed successfully.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BUILD SCALAR PAYLOAD — uppercase everything except preserve-case fields
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // SYNC RELATIONAL TABLES
    // Each table is wiped and re-inserted so the PDS always reflects the form.
    // ─────────────────────────────────────────────────────────────────────────

    private function syncRelational(Employee $employee, Request $request): void
    {
        $empId = $employee->id;

        // ── Children ──────────────────────────────────────────────────────────
        PdsChild::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'children') as $i => $row) {
            PdsChild::create([
                'employee_id'   => $empId,
                'name'          => $this->up($row['name'] ?? ''),
                'date_of_birth' => $row['date_of_birth'] ?? null,
                'order'         => $i,
            ]);
        }

        // ── Education ─────────────────────────────────────────────────────────
        PdsEducation::where('employee_id', $empId)->delete();
        $eduLevels = ['elementary', 'secondary', 'vocational', 'college', 'graduate'];
        foreach ($eduLevels as $level) {
            foreach ($this->decodeJson($request, "edu_{$level}") as $i => $row) {
                PdsEducation::create([
                    'employee_id'   => $empId,
                    'level'         => $level,
                    'school'        => $this->up($row['school']         ?? ''),
                    'degree'        => $this->up($row['degree']         ?? ''),
                    'period_from'   => $this->up($row['period_from']    ?? ''),
                    'period_to'     => $this->up($row['period_to']      ?? ''),
                    'highest_level' => $this->up($row['highest_level']  ?? ''),
                    'year_graduated'=> $this->up($row['year_graduated'] ?? ''),
                    'honors'        => $this->up($row['honors']         ?? ''),
                    'order'         => $i,
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
                'exam_date'   => $row['exam_date']   ?? null,
                'place'       => $this->up($row['place']       ?? ''),
                'license_no'  => $this->up($row['license_no']  ?? ''),
                'valid_until' => $row['valid_until'] ?? null,
                'order'       => $i,
            ]);
        }

        // ── Work Experience ───────────────────────────────────────────────────
        PdsWorkExperience::where('employee_id', $empId)->delete();
        foreach ($this->decodeJson($request, 'work_experiences') as $i => $row) {
            PdsWorkExperience::create([
                'employee_id'        => $empId,
                'date_from'          => $row['from']           ?? null,
                'date_to'            => $row['to']             ?? null,
                'position'           => $this->up($row['position']       ?? ''),
                'department'         => $this->up($row['department']     ?? ''),
                'monthly_salary'     => $row['monthly_salary']  ?? null,
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
                'date_from'        => $row['from'] ?? null,
                'date_to'          => $row['to']   ?? null,
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
                'date_from'    => $row['from'] ?? null,
                'date_to'      => $row['to']   ?? null,
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

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /** Decode a JSON-encoded array field from the request; return [] on failure. */
    private function decodeJson(Request $request, string $field): array
    {
        $raw = $request->input($field);
        if (! $raw) return [];
        if (is_array($raw)) return $raw;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** Uppercase + trim a string value; return empty string if blank. */
    private function up(mixed $value): string
    {
        if (! is_string($value)) return '';
        $trimmed = trim($value);
        return $trimmed !== '' ? strtoupper($trimmed) : '';
    }
}