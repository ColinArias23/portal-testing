<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('monthly_salary', 15, 2)
                ->nullable()
                ->after('annual_salary');
                // ->comment('Manually-entered/overridden gross monthly salary. For Plantilla, ignored unless salary_override is true.');

            $table->boolean('salary_override')
                ->default(false)
                ->after('monthly_salary');
                // ->comment('When true (Plantilla only), monthly_salary/annual_salary are used instead of the step increment values.');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['monthly_salary', 'salary_override']);
        });
    }
};