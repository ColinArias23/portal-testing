<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('first_day_of_service')
                ->nullable()
                ->after('consultant_position_id');

            $table->date('contract_period_from')
                ->nullable()
                ->after('first_day_of_service');

            $table->date('contract_period_to')
                ->nullable()
                ->after('contract_period_from');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'first_day_of_service',
                'contract_period_from',
                'contract_period_to',
            ]);
        });
    }
};
