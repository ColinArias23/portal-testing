<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | BASIC INFO
            |--------------------------------------------------------------------------
            */

            $table->string('employee_number')->unique();
            $table->string('role_position')->nullable();

            /*
            |--------------------------------------------------------------------------
            | ORGANIZATIONAL ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            // Employee belongs to a department
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | POSITION / PLANTILLA
            |--------------------------------------------------------------------------
            */

            $table->foreignId('plantilla_item_id')
                ->nullable()
                ->constrained('plantilla_items')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | SALARY STEP
            |--------------------------------------------------------------------------
            */

            $table->foreignId('step_increment_id')
                ->nullable()
                ->constrained('step_increments')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | NAME FIELDS
            |--------------------------------------------------------------------------
            */

            $table->string('prefix')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();

            $table->string('position_designation')->nullable();
            $table->string('title')->nullable();


            /*
            |--------------------------------------------------------------------------
            | EMPLOYMENT INFO
            |--------------------------------------------------------------------------
            */

            $table->enum('employment_type', ['Plantilla', 'Consultant', 'COS'])
                ->default('COS');

            $table->enum('employment_status', ['Active', 'Inactive', 'Resign'])
                ->default('Active');

            /*
            |--------------------------------------------------------------------------
            | ORG CHART SETTINGS
            |--------------------------------------------------------------------------
            */

            $table->string('avatar_url')->nullable();
            $table->string('border_color')->nullable();

            $table->boolean('aligned')->default(false);
            $table->boolean('expanded')->default(false);

            $table->text('notes')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index('department_id');
            $table->index('plantilla_item_id');
            $table->index('step_increment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};