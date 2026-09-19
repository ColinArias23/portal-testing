<?php

namespace App\Repositories\Shared;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    private const LIKE_ESCAPE = '!';
    private const MAX_SEARCH_WORDS = 5;

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
            'employee.info' => fn ($q) => $q->select(['id', 'employee_id', 'mobile_no']),
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

    /**
     * Server-side paginated, filterable list of approved, non-SuperAdmin users
     * (excluding the acting user) for the User Management module.
     *
     * @param  array{search?: ?string, role?: ?string}  $filters
     */
    public function paginateApprovedUsers(
        int $currentUserId,
        array $filters = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        $search = trim((string) ($filters['search'] ?? ''));
        $role   = trim((string) ($filters['role'] ?? ''));

        return User::query()
            ->select([
                'users.id', 'users.username', 'users.email', 'users.employee_id',
                'users.given_name', 'users.middle_name', 'users.last_name',
                'users.approved_at',
            ])
            ->with([
                'employee' => fn ($q) => $q->select([
                    'id', 'prefix', 'first_name', 'middle_name', 'last_name', 'suffix', 'role_position',
                ]),
                'roles:id,name',
                'permissions:id,name',
            ])
            ->where('users.approval_status', 'APPROVED')
            ->where('users.id', '!=', $currentUserId)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'SuperAdmin'))
            ->when($role !== '', fn (Builder $q) => $q->whereHas(
                'roles',
                fn ($r) => $r->where('name', $role)
            ))
            ->when($search !== '', fn (Builder $q) => $this->applySearch($q, $search))
            ->orderByDesc('users.approved_at')
            ->orderByDesc('users.id')
            ->paginate($perPage);
    }

    /**
     * Every whitespace-separated word must match at least one name/contact
     * column, so "juan cruz" finds first_name=Juan + last_name=Cruz.
     */
    private function applySearch(Builder $query, string $term): void
    {
        $words = array_slice(
            preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            0,
            self::MAX_SEARCH_WORDS,
        );

        foreach ($words as $word) {
            $pattern = '%' . $this->escapeLike($word) . '%';

            $query->where(function (Builder $q) use ($pattern) {
                foreach (['users.username', 'users.email', 'users.given_name', 'users.last_name'] as $column) {
                    $q->orWhereRaw($this->likeSql($column), [$pattern]);
                }

                $q->orWhereHas('employee', function (Builder $e) use ($pattern) {
                    $e->where(function (Builder $inner) use ($pattern) {
                        foreach (['employees.first_name', 'employees.middle_name', 'employees.last_name'] as $column) {
                            $inner->orWhereRaw($this->likeSql($column), [$pattern]);
                        }
                    });
                });
            });
        }
    }

    private function likeSql(string $column): string
    {
        return $column . " LIKE ? ESCAPE '" . self::LIKE_ESCAPE . "'";
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'],
            $value,
        );
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
