<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_postings', function (Blueprint $table) {
            $table->decimal('annual_salary', 12, 2)->nullable()->after('monthly_salary');
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_postings', function (Blueprint $table) {
            $table->dropColumn('annual_salary');
        });
    }
};