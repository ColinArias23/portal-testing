<?php

namespace App\Services\Users;

use App\Events\RBAC\UserActivated;
use App\Support\Broadcasting\SafeBroadcast;
use App\Models\ActivityLog;
use App\Models\User;
use App\Repositories\Shared\EmployeeRepository;
use App\Repositories\Shared\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserActivationService
{
    private const GUARD = 'web';

    public function __construct(
        private readonly UserRepository $users,
        private readonly EmployeeRepository $employees,
        private readonly EmployeeLinkService $employeeLink,
    ) {
    }

    public function activate(User $user, int $activatedBy, Request $request): User
    {
        DB::transaction(function () use ($user, $activatedBy) {
            $this->employeeLink->link($user);

            $this->users->approveOne($user->id, $activatedBy);

            if ($user->employee_id) {
                $this->employees->updateEmploymentStatus($user->employee_id, 'ACTIVE');
            }

            $user->refresh();
            $this->assignDefaultRoleIfMissing($user);
        });

        $fresh = $this->users->findWithProfile($user->id) ?? $user->fresh(['employee.info']);

        ActivityLog::record(
            action: 'user.activated',
            userId: $activatedBy,
            subjectType: User::class,
            subjectId: $user->id,
            subjectLabel: $fresh->username,
            payload: [
                'target_username' => $fresh->username,
                'employee_id'     => $fresh->employee_id,
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        SafeBroadcast::send(new UserActivated($user->id, $fresh->employee_id));

        return $fresh;
    }

    public function bulkActivate(array $ids, int $activatedBy, Request $request): array
    {
        $users = $this->users->findPendingByIds($ids);

        if ($users->isEmpty()) {
            return ['count' => 0, 'users' => $users];
        }

        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => self::GUARD]);

        DB::transaction(function () use ($users, $activatedBy, $staffRole) {
            foreach ($users as $user) {
                $this->employeeLink->link($user);
            }

            $userIds = $users->pluck('id')->all();
            $this->users->bulkApprove($userIds, $activatedBy);

            $employeeIds = $users->fresh()->pluck('employee_id')->filter()->values()->all();
            $this->employees->updateEmploymentStatusForMany($employeeIds, 'ACTIVE');

            $this->assignDefaultRoleToUsersMissingOne($users->fresh(), $staffRole);
        });

        $fresh = $this->users->withEmployeeInfoByIds($ids);

        foreach ($users as $user) {
            $userData = $fresh[$user->id];

            ActivityLog::record(
                action: 'user.activated',
                userId: $activatedBy,
                subjectType: User::class,
                subjectId: $user->id,
                subjectLabel: $userData->username,
                payload: [
                    'target_username' => $userData->username,
                    'employee_id'     => $userData->employee_id,
                    'bulk'            => true,
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            SafeBroadcast::send(new UserActivated($user->id, $userData->employee_id));
        }

        return ['count' => $users->count(), 'users' => $users];
    }

    private function assignDefaultRoleIfMissing(User $user): void
    {
        if ($user->roles->isEmpty()) {
            $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => self::GUARD]);
            $user->assignRole($staffRole);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function assignDefaultRoleToUsersMissingOne($users, Role $staffRole): void
    {
        $usersNeedingRole = $users->filter(fn (User $u) => $u->roles->isEmpty());

        if ($usersNeedingRole->isEmpty()) {
            return;
        }

        foreach ($usersNeedingRole as $user) {
            $user->assignRole($staffRole);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}