<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignId('head_employee_id')->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['department_id', 'code']);
            $table->index('head_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
