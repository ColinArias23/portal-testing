<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leave_types', 'enforce_annual_cap')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->boolean('enforce_annual_cap')
                    ->default(false)
                    ->after('max_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_types', 'enforce_annual_cap')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropColumn('enforce_annual_cap');
            });
        }
    }
};