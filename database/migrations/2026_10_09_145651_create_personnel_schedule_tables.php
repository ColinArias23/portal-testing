<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psched_divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['OFFICE', 'DIRECTORATE', 'DIVISION'])->default('DIVISION');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('psched_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('psched_divisions')->restrictOnDelete();
            $table->string('name');
            $table->enum('type', ['DEPARTMENT', 'SECTION', 'UNIT', 'CLUSTER'])->default('DEPARTMENT');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['division_id', 'name']);
        });

        Schema::create('psched_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('psched_divisions')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('psched_units')->restrictOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['division_id', 'unit_id']);
            $table->index('sort_order');
        });

        Schema::create('psched_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained('psched_lines')->cascadeOnDelete();
            $table->string('item_number', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('employee_number', 50)->nullable();
            $table->string('employee_name')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->index(['line_id', 'sort_order']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psched_slots');
        Schema::dropIfExists('psched_lines');
        Schema::dropIfExists('psched_units');
        Schema::dropIfExists('psched_divisions');
    }
};