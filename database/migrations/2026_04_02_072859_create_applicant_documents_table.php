<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('applicant_id')
                ->constrained('applicants')
                ->cascadeOnDelete();

            $table->string('name', 100);              
            $table->string('status', 30)->default('Pending');
            $table->string('file_path')->nullable();  

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_documents');
    }
};