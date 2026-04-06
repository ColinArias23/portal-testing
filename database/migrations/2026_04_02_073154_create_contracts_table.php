<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('applicant_id')->nullable()
                ->constrained('applicants')
                ->nullOnDelete();

            $table->string('employee_name', 150);
            $table->string('position', 100)->nullable();
            $table->decimal('salary', 12, 2)->nullable();
            $table->dateTime('start_date')->nullable();
            $table->string('status', 30)->default('Draft'); // Draft, Generated, Signed
            $table->string('file_path')->nullable();        // generated PDF path

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};