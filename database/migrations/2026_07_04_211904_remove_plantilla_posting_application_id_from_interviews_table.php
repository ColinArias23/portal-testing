<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Decouples the Hiring `interviews` table from the Plantilla/PSB flow,
     * which now has its own `plantilla_posting_application_interviews` table.
     *
     * Any existing PSB-related rows in `interviews` are copied over first,
     * so no data is lost even if this flow was already used before this migration.
     */
    public function up(): void
    {
        if (Schema::hasColumn('interviews', 'plantilla_posting_application_id')) {
            $legacyRows = DB::table('interviews')
                ->whereNotNull('plantilla_posting_application_id')
                ->get();

            foreach ($legacyRows as $row) {
                DB::table('plantilla_posting_application_interviews')->updateOrInsert(
                    ['plantilla_posting_application_id' => $row->plantilla_posting_application_id],
                    [
                        'hr_status'          => $row->hr_status,
                        'head_status'        => $row->head_status,
                        'final_status'       => $row->final_status,
                        'overall_status'     => $row->overall_status,
                        'hr_scheduled_at'    => $row->hr_scheduled_at,
                        'head_scheduled_at'  => $row->head_scheduled_at,
                        'final_scheduled_at' => $row->final_scheduled_at,
                        'hr_notes'           => $row->hr_notes,
                        'head_notes'         => $row->head_notes,
                        'final_notes'        => $row->final_notes,
                        'created_at'         => $row->created_at,
                        'updated_at'         => $row->updated_at,
                    ]
                );
            }

            Schema::table('interviews', function (Blueprint $table) {
                $table->dropIndex('interviews_pp_application_status_index');
                $table->dropForeign(['plantilla_posting_application_id']);
                $table->dropColumn('plantilla_posting_application_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->foreignId('plantilla_posting_application_id')
                ->nullable()
                ->after('applicant_id')
                ->constrained('plantilla_posting_applications')
                ->cascadeOnDelete();

            $table->index(
                ['plantilla_posting_application_id', 'overall_status'],
                'interviews_pp_application_status_index'
            );
        });
    }
};