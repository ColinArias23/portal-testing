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
            $table->json('role_position')->nullable();   // ← now JSON array

            /*
            |--------------------------------------------------------------------------
            | ORGANIZATIONAL ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            $table->foreignId('division_id')
                ->nullable()
                ->constrained('divisions')
                ->nullOnDelete();

            // Employee can belong to many departments (stored as JSON array)
            $table->json('department_ids')->nullable();  // ← replaces department_id

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

            $table->json('position_designation')->nullable();
            $table->string('title')->nullable();

            /*
            |--------------------------------------------------------------------------
            | EMPLOYMENT INFO
            |--------------------------------------------------------------------------
            */

            $table->enum('employment_type', ['Plantilla', 'Consultant', 'Contract of Service'])
                ->default('Contract of Service');

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

            $table->index('division_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};