<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_info', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete()
                ->unique();

            // ── Legacy / mirror fields ────────────────────────────────────
            $table->string('contact', 50)->nullable();
            $table->text('address')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('gender', 10)->nullable();

            // ── I. Personal Information ───────────────────────────────────
            $table->string('surname', 100)->nullable();
            $table->string('name_extension', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('place_of_birth')->nullable();
            $table->string('sex_at_birth', 10)->nullable();
            $table->string('civil_status', 30)->nullable();
            $table->decimal('height', 4, 2)->nullable();
            $table->decimal('weight', 5, 1)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->string('citizenship', 60)->nullable();

            // Government IDs
            $table->string('umid_id', 30)->nullable();
            $table->string('pagibig_id', 30)->nullable();
            $table->string('philhealth_no', 30)->nullable();
            $table->string('philsys_number', 30)->nullable();
            $table->string('tin_no', 30)->nullable();
            $table->string('agency_employee_no', 50)->nullable();

            // Residential address
            $table->string('residential_province', 100)->nullable();
            $table->string('residential_city', 100)->nullable();
            $table->string('residential_barangay', 100)->nullable();
            $table->string('residential_house_no', 50)->nullable();
            $table->string('residential_street', 100)->nullable();
            $table->string('residential_subdivision', 100)->nullable();
            $table->string('residential_zip', 10)->nullable();

            // Permanent address
            $table->string('permanent_province', 100)->nullable();
            $table->string('permanent_city', 100)->nullable();
            $table->string('permanent_barangay', 100)->nullable();
            $table->string('permanent_house_no', 50)->nullable();
            $table->string('permanent_street', 100)->nullable();
            $table->string('permanent_subdivision', 100)->nullable();
            $table->string('permanent_zip', 10)->nullable();

            // Contact
            $table->string('telephone_no', 30)->nullable();
            $table->string('mobile_no', 30)->nullable();
            $table->string('email_address', 100)->nullable();

            // ── II. Family Background — Spouse ────────────────────────────
            $table->string('spouse_surname', 100)->nullable();
            $table->string('spouse_first_name', 100)->nullable();
            $table->string('spouse_middle_name', 100)->nullable();
            $table->string('spouse_extension', 20)->nullable();
            $table->string('spouse_occupation', 100)->nullable();
            $table->string('spouse_employer', 100)->nullable();
            $table->text('spouse_business_address')->nullable();
            $table->string('spouse_telephone', 30)->nullable();

            // Father
            $table->string('father_surname', 100)->nullable();
            $table->string('father_first_name', 100)->nullable();
            $table->string('father_middle_name', 100)->nullable();
            $table->string('father_extension', 20)->nullable();

            // Mother
            $table->string('mother_maiden_surname', 100)->nullable();
            $table->string('mother_first_name', 100)->nullable();
            $table->string('mother_middle_name', 100)->nullable();

            // ── IX. Questions ─────────────────────────────────────────────
            $table->string('related_3rd_degree', 5)->nullable();
            $table->text('related_3rd_degree_details')->nullable();
            $table->string('related_4th_degree', 5)->nullable();
            $table->text('related_4th_degree_details')->nullable();
            $table->string('found_guilty_admin', 5)->nullable();
            $table->text('found_guilty_admin_details')->nullable();
            $table->string('criminally_charged', 5)->nullable();
            $table->text('criminally_charged_details')->nullable();
            $table->string('convicted_crime', 5)->nullable();
            $table->text('convicted_crime_details')->nullable();
            $table->string('separated_from_service', 5)->nullable();
            $table->text('separated_from_service_details')->nullable();
            $table->string('candidate_in_election', 5)->nullable();
            $table->text('candidate_in_election_details')->nullable();
            $table->string('resigned_to_campaign', 5)->nullable();
            $table->text('resigned_to_campaign_details')->nullable();
            $table->string('immigrant_status', 5)->nullable();
            $table->string('immigrant_country', 100)->nullable();
            $table->string('is_indigenous', 5)->nullable();
            $table->string('indigenous_specify', 100)->nullable();
            $table->string('is_pwd', 5)->nullable();
            $table->string('pwd_id_no', 50)->nullable();
            $table->string('is_solo_parent', 5)->nullable();
            $table->string('solo_parent_id_no', 50)->nullable();

            // ── Government-issued ID ──────────────────────────────────────
            $table->string('govt_id_type', 60)->nullable();
            $table->string('govt_id_no', 60)->nullable();
            $table->string('govt_id_date_place', 100)->nullable();

            // ── Professional License ──────────────────────────────────────
            $table->string('license_number', 60)->nullable();
            $table->date('license_date_issued')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->string('license_place_issued', 100)->nullable();

            $table->enum('status', ['COMPLETE', 'INCOMPLETE', 'EMPTY'])->default('EMPTY');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_info');
    }
};