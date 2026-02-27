<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_hierarchy', function (Blueprint $table) {
            $table->id();

            // The parent (supervisor / manager)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();


            // The employee (child)
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_hierarchy');
    }
};
