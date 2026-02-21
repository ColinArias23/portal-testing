<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('division_employee', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();

            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->unique(['employee_id', 'division_id']);
            $table->index(['division_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('division_employee');
    }
};
