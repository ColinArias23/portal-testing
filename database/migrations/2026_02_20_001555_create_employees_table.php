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

            $table->string('employee_number')->nullable();
            $table->json('role_position')->nullable();

            $table->foreignId('division_id')
                ->nullable()
                ->constrained('divisions')
                ->nullOnDelete();

            $table->json('department_ids')->nullable();

            $table->string('prefix')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix')->nullable();

            $table->json('position_designation')->nullable();
            $table->json('title')->nullable();

            $table->enum('employment_type', ['Plantilla', 'Consultant', 'Contract of Service'])
                ->default('Plantilla');

            $table->string('employment_status')->nullable();

            $table->unsignedBigInteger('cos_position_id')
                ->nullable()
                ->comment('FK to cos_positions for Contract of Service employees');

            $table->unsignedBigInteger('consultant_position_id')
                ->nullable()
                ->comment('FK to consultant_positions for Consultant employees');

            // Foreign key constraints
            $table->foreign('cos_position_id')
                ->references('id')
                ->on('cos_positions')
                ->onDelete('set null');

            $table->foreign('consultant_position_id')
                ->references('id')
                ->on('consultant_positions')
                ->onDelete('set null');

            // ── Avatar & UI ────────────────────────────────────────────────────

            $table->string('avatar_url')->nullable();
            $table->string('border_color')->nullable();

            $table->boolean('aligned')->default(false);
            $table->boolean('expanded')->default(false);

            $table->string('notes')->nullable();

            $table->timestamps();

            // ── Indexes ────────────────────────────────────────────────────────

            $table->index('division_id');
            $table->index('employment_type');
            $table->index('cos_position_id');
            $table->index('consultant_position_id');
            $table->index(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};