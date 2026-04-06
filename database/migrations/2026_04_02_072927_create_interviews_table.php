<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('applicant_id')
                ->constrained('applicants')
                ->cascadeOnDelete();

            // Interview stages
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

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};