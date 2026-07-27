<?php

namespace App\Repositories\Shared;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    private const USER_COLUMNS = [
        'id', 'employee_id', 'username', 'email', 'password',
        'given_name', 'middle_name', 'last_name',
        'approval_status', 'approved_at', 'has_completed_orientation',
        'created_at', 'updated_at',
    ];

    public function findByUsernameForAuth(string $username): ?User
    {
        return User::query()
            ->where('username', $username)
            ->first(self::USER_COLUMNS);
    }

    public function findWithProfile(int $userId): ?User
    {
        return User::query()
            ->select(self::USER_COLUMNS)
            ->where('id', $userId)
            ->with($this->profileRelations())
            ->first();
    }

    public function loadProfile(User $user): User
    {
        return $user->load($this->profileRelations());
    }

    private function profileRelations(): array
    {
        return [
            'employee' => fn ($q) => $q->select([
                'id', 'employee_number', 'division_id', 'department_ids',
                'first_name', 'middle_name', 'last_name',
                'employment_type', 'employment_status',
                'position_designation', 'role_position',
                'cos_position_id', 'consultant_position_id', 'avatar_url',
            ]),
            'employee.info' => fn ($q) => $q->select(['id', 'employee_id']),
            'employee.division' => fn ($q) => $q->select(['id', 'name']),
            'employee.primaryDepartmentAssignment' => fn ($q) => $q->select(
                ['id', 'employee_id', 'department_id', 'is_primary', 'end_date']
            ),
            'employee.primaryDepartmentAssignment.department' => fn ($q) => $q->select(['id', 'name']),
            'employee.primaryAssignment' => fn ($q) => $q->select([
                'employee_assignments.id',
                'employee_assignments.employee_id',
                'employee_assignments.plantilla_position_id',
                'employee_assignments.is_primary',
                'employee_assignments.start_date',
                'employee_assignments.end_date',
            ]),
            'employee.primaryAssignment.plantillaPosition' => fn ($q) => $q->select(
                ['id', 'position_title']
            ),
            'employee.cosPosition' => fn ($q) => $q->select(['id', 'title']),
            'employee.consultantPosition' => fn ($q) => $q->select(['id', 'title']),
        ];
    }

    public function usernameExists(string $username): bool
    {
        return User::where('username', $username)->exists();
    }

    public function usernamesTaken(array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }

        return User::whereIn('username', $candidates)->pluck('username')->all();
    }

    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function pendingCount(): int
    {
        return User::where('approval_status', 'PENDING')->count();
    }

    public function pendingUsers(): Collection
    {
        return User::where('approval_status', 'PENDING')
            ->select(self::USER_COLUMNS)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function approvedUsers(int $currentUserId): Collection
    {
        return User::query()
            ->select(['id', 'username', 'email', 'employee_id', 'approved_at'])
            ->with([
                'employee' => fn ($q) => $q->select(['id', 'first_name', 'middle_name', 'last_name', 'role_position']),
                'roles:id,name',
                'permissions:id,name',
            ])
            ->where('approval_status', 'APPROVED')
            ->where('id', '!=', $currentUserId)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'SuperAdmin'))
            ->orderBy('approved_at', 'desc')
            ->get();
    }

    public function approvedUsersCursorPaginated(int $currentUserId, int $perPage = 50)
    {
        return User::query()
            ->select(['id', 'username', 'email', 'employee_id', 'approved_at'])
            ->with([
                'employee' => fn ($q) => $q->select(['id', 'first_name', 'middle_name', 'last_name', 'role_position']),
                'roles:id,name',
                'permissions:id,name',
            ])
            ->where('approval_status', 'APPROVED')
            ->where('id', '!=', $currentUserId)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'SuperAdmin'))
            ->orderBy('approved_at', 'desc')
            ->cursorPaginate($perPage);
    }

    public function findByIds(array $ids): Collection
    {
        return User::whereIn('id', $ids)->get();
    }

    public function findPendingByIds(array $ids): Collection
    {
        return User::whereIn('id', $ids)
            ->where('approval_status', 'PENDING')
            ->get(['id', 'employee_id', 'given_name', 'middle_name', 'last_name']);
    }

    public function bulkApprove(array $ids, int $approvedBy): void
    {
        DB::table('users')->whereIn('id', $ids)->update([
            'approval_status' => 'APPROVED',
            'approved_at'     => now()->toDateTimeString(),
            'approved_by'     => $approvedBy,
            'updated_at'      => now()->toDateTimeString(),
        ]);
    }

    public function approveOne(int $userId, int $approvedBy): void
    {
        DB::table('users')->where('id', $userId)->update([
            'approval_status' => 'APPROVED',
            'approved_at'     => now()->toDateTimeString(),
            'approved_by'     => $approvedBy,
            'updated_at'      => now()->toDateTimeString(),
        ]);
    }

    public function withEmployeeInfoByIds(array $ids): Collection
    {
        return User::whereIn('id', $ids)
            ->with('employee.info')
            ->get()
            ->keyBy('id');
    }

    public function lockForUpdate(int $id): ?User
    {
        return User::lockForUpdate()->find($id);
    }
}
