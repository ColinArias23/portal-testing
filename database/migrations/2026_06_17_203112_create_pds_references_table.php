<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_references', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->string('name', 150)->nullable();
            $table->string('address', 250)->nullable();
            $table->string('contact', 100)->nullable();
            $table->unsignedInteger('order')->default(0);

            $table->timestamps();

            $table->index(['employee_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_references');
    }
};