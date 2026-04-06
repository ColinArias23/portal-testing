<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hiring_event_applicants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hiring_event_id')
                ->constrained('hiring_events')
                ->cascadeOnDelete();

            $table->foreignId('applicant_id')
                ->constrained('applicants')
                ->cascadeOnDelete();

            $table->unique(['hiring_event_id', 'applicant_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_event_applicants');
    }
};