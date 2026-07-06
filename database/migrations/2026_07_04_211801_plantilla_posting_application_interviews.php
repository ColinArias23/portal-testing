<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_posting_application_interviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plantilla_posting_application_id')
                ->constrained(
                    'plantilla_posting_applications',
                    'id',
                    'ppa_interviews_application_id_foreign'
                )
                ->cascadeOnDelete()
                ->unique('ppa_interviews_application_id_unique');

            $table->string('hr_status', 30)->default('Pending');
            $table->string('head_status', 30)->default('Pending');
            $table->string('final_status', 30)->default('Pending');
            $table->string('overall_status', 60)->default('Pending');

            $table->dateTime('hr_scheduled_at')->nullable();
            $table->dateTime('head_scheduled_at')->nullable();
            $table->dateTime('final_scheduled_at')->nullable();

            $table->text('hr_notes')->nullable();
            $table->text('head_notes')->nullable();
            $table->text('final_notes')->nullable();

            $table->index('overall_status');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_posting_application_interviews');
    }
};