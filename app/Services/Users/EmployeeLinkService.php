<?php

namespace App\Services\Users;

use App\Models\User;
use App\Repositories\Shared\EmployeeRepository;
use App\Repositories\Shared\UserRepository;
use Illuminate\Support\Facades\DB;

class EmployeeLinkService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EmployeeRepository $employees,
    ) {
    }

    public function link(User $user): void
    {
        $user->refresh();
        if ($user->employee_id) {
            return;
        }

        DB::transaction(function () use ($user) {
            $lockedUser = $this->users->lockForUpdate($user->id);

            if ($lockedUser->employee_id) {
                return;
            }

            $employeeId = $this->employees->createBareEmployee([
                'first_name'        => $lockedUser->given_name ?? null,
                'middle_name'       => $lockedUser->middle_name ?? null,
                'last_name'         => $lockedUser->last_name ?? null,
                'employment_type'   => 'Contract of Service',
                'employment_status' => 'pending',
                'aligned'           => false,
                'expanded'          => false,
            ]);

            DB::table('users')
                ->where('id', $lockedUser->id)
                ->update([
                    'employee_id' => $employeeId,
                    'updated_at'  => now()->toDateTimeString(),
                ]);

            $user->employee_id = $employeeId;
        });
    }
}
