<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeInfo extends Model
{

    use AutoUppercase;

    protected $table = 'employee_info';

    protected array $uppercaseExclude = [
        'email_address',
        'date_of_birth',
        'license_date_issued',
        'license_expiry_date',
        'govt_id_date_place',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        // ── Legacy fields (kept for backwards compat) ─────────────────────
        'employee_id',
        'contact',      
        'address',      
        'birthdate',   
        'gender',       
        'status',

        // ── I. Personal Information ───────────────────────────────────────
        'surname',
        'name_extension',
        'date_of_birth',
        'place_of_birth',
        'sex_at_birth',
        'civil_status',
        'height',
        'weight',
        'blood_type',
        'citizenship',

        // Government IDs
        'umid_id',
        'pagibig_id',
        'philhealth_no',
        'sss_no',
        'philsys_number',
        'tin_no',
        'agency_employee_no',

        // Residential address (No. 17)
        'residential_province',
        'residential_city',
        'residential_barangay',
        'residential_house_no',
        'residential_street',
        'residential_subdivision',
        'residential_zip',

        // Permanent address (No. 18)
        'permanent_province',
        'permanent_city',
        'permanent_barangay',
        'permanent_house_no',
        'permanent_street',
        'permanent_subdivision',
        'permanent_zip',

        // Contact (Nos. 19-21)
        'telephone_no',
        'mobile_no',
        'email_address',

        // ── II. Family Background ─────────────────────────────────────────
        'spouse_surname',
        'spouse_first_name',
        'spouse_middle_name',
        'spouse_extension',
        'spouse_occupation',
        'spouse_employer',
        'spouse_business_address',
        'spouse_telephone',
        'father_surname',
        'father_first_name',
        'father_middle_name',
        'father_extension',
        'mother_maiden_surname',
        'mother_first_name',
        'mother_middle_name',

        // ── IX. Questions ─────────────────────────────────────────────────
        'related_3rd_degree',
        'related_3rd_degree_details',
        'related_4th_degree',
        'related_4th_degree_details',
        'found_guilty_admin',
        'found_guilty_admin_details',
        'criminally_charged',
        'criminally_charged_details',
        'convicted_crime',
        'convicted_crime_details',
        'separated_from_service',
        'separated_from_service_details',
        'candidate_in_election',
        'candidate_in_election_details',
        'resigned_to_campaign',
        'resigned_to_campaign_details',
        'immigrant_status',
        'immigrant_country',
        'is_indigenous',
        'indigenous_specify',
        'is_pwd',
        'pwd_id_no',
        'is_solo_parent',
        'solo_parent_id_no',

        // ── Government-issued ID ──────────────────────────────────────────
        'govt_id_type',
        'govt_id_type_other',
        'govt_id_no',
        'govt_id_date_place',

        // ── Professional License ──────────────────────────────────────────
        'license_number',
        'license_date_issued',
        'license_expiry_date',
        'license_place_issued',
    ];

    private const REQUIRED_FIELDS = [
        'date_of_birth',
        'sex_at_birth',
        'civil_status',
        'citizenship',
        'residential_city',
        'residential_province',
        'mobile_no',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeInfo $info) {
            $info->status = $info->computeStatus();

            if ($info->isDirty('date_of_birth') && !$info->isDirty('birthdate')) {
                $info->birthdate = $info->date_of_birth;
            }
            if ($info->isDirty('sex_at_birth') && !$info->isDirty('gender')) {
                $info->gender = $info->sex_at_birth;
            }
            if ($info->isDirty('mobile_no') && !$info->isDirty('contact')) {
                $info->contact = $info->mobile_no;
            }
            if ($info->isDirty('residential_city') && !$info->isDirty('address')) {
                $city     = $info->residential_city     ?? '';
                $province = $info->residential_province ?? '';
                $info->address = trim("{$city}, {$province}", ', ');
            }
        });
    }

    public function computeStatus(): string
    {
        $infoFilled = collect(self::REQUIRED_FIELDS)
            ->filter(fn($field) => !empty($this->$field))
            ->count();

        $employee = $this->employee;

        $nameFilled   = (!empty($employee?->first_name) && !empty($employee?->last_name)) ? 1 : 0;
        $avatarFilled = $this->resolveAvatarExists($employee) ? 1 : 0;

        $totalRequired = count(self::REQUIRED_FIELDS) + 2;
        $totalFilled   = $infoFilled + $nameFilled + $avatarFilled;

        if ($totalFilled === 0)              return 'EMPTY';
        if ($totalFilled === $totalRequired) return 'COMPLETE';
        return 'INCOMPLETE';
    }

    private function resolveAvatarExists(?Employee $employee): bool
    {
        $rawPath = $employee?->getRawOriginal('avatar_url');
        return $rawPath && file_exists(storage_path('app/public/' . $rawPath));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}