<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->whereNotNull('employee_number')
            ->orderBy('id')
            ->chunkById(200, function ($employees) {
                foreach ($employees as $employee) {
                    $normalized = strtoupper(trim($employee->employee_number));
                    if ($normalized !== $employee->employee_number) {
                        DB::table('employees')
                            ->where('id', $employee->id)
                            ->update(['employee_number' => $normalized]);
                    }
                }
            });

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('employee_number', 'employees_employee_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_employee_number_unique');
        });
    }
};