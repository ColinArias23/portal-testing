<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_trainings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->string('title', 200)->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('hours', 30)->nullable();
            $table->string('type', 100)->nullable();
            $table->string('sponsored_by', 200)->nullable();
            $table->unsignedInteger('order')->default(0);

            $table->timestamps();

            $table->index(['employee_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_trainings');
    }
};