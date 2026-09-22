<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('employee_id')
            ->where(function ($q) {
                $q->whereNotNull('given_name')->orWhereNotNull('last_name');
            })
            ->where('email', 'not like', '%@local.test')
            ->orderBy('id')
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    DB::transaction(function () use ($user) {
                        $locked = DB::table('users')
                            ->where('id', $user->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $locked || $locked->employee_id) {
                            return;
                        }

                        $now = now()->toDateTimeString();

                        $employeeId = DB::table('employees')->insertGetId([
                            'first_name'        => $locked->given_name,
                            'middle_name'       => $locked->middle_name,
                            'last_name'         => $locked->last_name,
                            'employment_type'   => 'Contract of Service',
                            'employment_status' => 'pending',
                            'aligned'           => false,
                            'expanded'          => false,
                            'created_at'        => $now,
                            'updated_at'        => $now,
                        ]);

                        DB::table('employee_info')->insert([
                            'employee_id' => $employeeId,
                            'created_at'  => $now,
                            'updated_at'  => $now,
                        ]);

                        DB::table('users')->where('id', $locked->id)->update([
                            'employee_id' => $employeeId,
                            'updated_at'  => $now,
                        ]);
                    });
                }
            });
    }

    public function down(): void
    {
        //
    }
};