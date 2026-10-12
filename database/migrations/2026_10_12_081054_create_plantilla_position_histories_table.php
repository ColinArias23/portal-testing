<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_position_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plantilla_position_id')
                ->nullable()
                ->constrained('plantilla_positions')
                ->nullOnDelete();
            $table->string('position_label', 100)->nullable();

            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();
            $table->string('employee_name')->nullable();

            $table->string('event_type', 20);

            $table->foreignId('related_position_id')
                ->nullable()
                ->constrained('plantilla_positions')
                ->nullOnDelete();
            $table->string('related_position_label', 100)->nullable();

            $table->foreignId('plantilla_posting_application_id')
                ->nullable()
                ->constrained('plantilla_posting_applications', 'id', 'pph_application_foreign')
                ->nullOnDelete();

            $table->foreignId('employee_assignment_id')
                ->nullable()
                ->constrained('employee_assignments')
                ->nullOnDelete();

            $table->date('effective_date');
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['plantilla_position_id', 'effective_date', 'id'], 'pph_position_timeline_index');
        });

        $this->backfillFromAssignments();
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_position_histories');
    }

    private function backfillFromAssignments(): void
    {
        $name = "TRIM(CONCAT_WS(' ', NULLIF(e.prefix, ''), NULLIF(e.first_name, ''), NULLIF(e.middle_name, ''), NULLIF(e.last_name, ''), NULLIF(e.suffix, '')))";

        DB::statement("
            INSERT INTO plantilla_position_histories
                (plantilla_position_id, position_label, employee_id, employee_name, event_type,
                 employee_assignment_id, effective_date, created_at, updated_at)
            SELECT plantilla_position_id, position_label, employee_id, employee_name, event_type,
                   employee_assignment_id, effective_date, NOW(), NOW()
            FROM (
                SELECT a.plantilla_position_id, p.position_slot_name AS position_label,
                       a.employee_id, {$name} AS employee_name,
                       'ASSIGNED' AS event_type, a.id AS employee_assignment_id,
                       a.start_date AS effective_date, 0 AS event_rank
                FROM employee_assignments a
                JOIN plantilla_positions p ON p.id = a.plantilla_position_id
                LEFT JOIN employees e ON e.id = a.employee_id
                WHERE a.plantilla_position_id IS NOT NULL

                UNION ALL

                SELECT a.plantilla_position_id, p.position_slot_name,
                       a.employee_id, {$name},
                       'VACATED', a.id,
                       a.end_date, 1
                FROM employee_assignments a
                JOIN plantilla_positions p ON p.id = a.plantilla_position_id
                LEFT JOIN employees e ON e.id = a.employee_id
                WHERE a.plantilla_position_id IS NOT NULL
                  AND a.end_date IS NOT NULL
            ) AS events
            ORDER BY effective_date, employee_assignment_id, event_rank
        ");
    }
};
