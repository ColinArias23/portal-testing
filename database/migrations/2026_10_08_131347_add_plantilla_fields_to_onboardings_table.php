<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboardings', function (Blueprint $table) {
            $table->foreignId('plantilla_posting_application_id')
                ->nullable()
                ->after('applicant_id')
                ->unique('onboardings_psb_application_unique')
                ->constrained('plantilla_posting_applications')
                ->nullOnDelete();

            $table->string('type', 20)->nullable()->after('status');
            $table->timestamp('provisioned_at')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('onboardings', function (Blueprint $table) {
            $table->dropForeign(['plantilla_posting_application_id']);
            $table->dropUnique('onboardings_psb_application_unique');
            $table->dropColumn(['plantilla_posting_application_id', 'type', 'provisioned_at']);
        });
    }
};
