<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_postings', function (Blueprint $table) {
            $table->id();

            $table->string('base_item_number')->index();
            $table->string('title');
            $table->text('description')->nullable();

            $table->foreignId('display_department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();
            $table->foreignId('display_division_id')
                ->nullable()
                ->constrained('divisions')
                ->nullOnDelete();
            $table->string('section')->nullable();

            $table->foreignId('salary_grade_id')
                ->nullable()
                ->constrained('salary_grades')
                ->nullOnDelete();
            $table->foreignId('step_increment_id')
                ->nullable()
                ->constrained('step_increments')
                ->nullOnDelete();
            $table->decimal('monthly_salary', 12, 2)->nullable();

            $table->enum('employment_status', ['Permanent'])
                ->default('Permanent');

            $table->unsignedTinyInteger('vacancies')->default(1);

            $table->string('immediate_supervisor')->nullable();

            $table->text('qualification_education')->nullable();
            $table->text('qualification_experience')->nullable();
            $table->text('qualification_training')->nullable();
            $table->text('qualification_eligibility')->nullable();
            $table->text('qualification_competency')->nullable();

            $table->text('job_description');

            $table->json('required_documents');

            $table->date('date_posted');
            $table->date('closing_date');
            $table->date('expected_appointment_date')->nullable();

            $table->enum('status', ['Open', 'Closing Soon', 'Closed'])
                ->default('Open');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_postings');
    }
};