<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('salary_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('salary_grade')->unique(); // SG number
            $table->decimal('monthly_salary', 12, 2)->nullable();
            $table->decimal('annual_salary', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_grades');
    }
};
