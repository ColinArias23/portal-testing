<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_info', function (Blueprint $table) {
            $table->string('sss_no', 30)->nullable()->after('umid_id');
            $table->string('govt_id_type_other', 100)->nullable()->after('govt_id_type');
        });
    }

    public function down(): void
    {
        Schema::table('employee_info', function (Blueprint $table) {
            $table->dropColumn(['sss_no', 'govt_id_type_other']);
        });
    }
};