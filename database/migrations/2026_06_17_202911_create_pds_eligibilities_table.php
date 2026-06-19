<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_eligibilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->string('eligibility', 150)->nullable();
            $table->string('rating', 30)->nullable();
            $table->date('exam_date')->nullable();
            $table->string('place', 150)->nullable();
            $table->string('license_no', 60)->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('order')->default(0);

            $table->timestamps();

            $table->index(['employee_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_eligibilities');
    }
};