<?php

namespace App\Repositories\RBAC;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditLogRepository
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ActivityLog::with('user:id,username')->orderByDesc('performed_at');

        $this->excludeSuperAdmin($query);
        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    public function paginateByActionPrefix(string $prefix, array $filters, int $perPage): LengthAwarePaginator
    {
        // AutoUppercase stores actions and labels in uppercase, so match in uppercase too.
        $query = ActivityLog::with([
            'user:id,username,employee_id',
            'user.employee:id,employee_number,first_name,middle_name,last_name',
        ])
            ->where('action', 'like', addcslashes(strtoupper($prefix), '%_\\') . '%')
            ->orderByDesc('performed_at')
            ->orderByDesc('id');

        $this->excludeSuperAdmin($query);
        $this->applyFilters($query, $filters);

        if (! empty($filters['q'])) {
            $query->where('subject_label', 'like', '%' . addcslashes(strtoupper($filters['q']), '%_\\') . '%');
        }

        return $query->paginate($perPage);
    }

    public function distinctActions(): Collection
    {
        $query = ActivityLog::query();
        $this->excludeSuperAdmin($query);

        return $query->distinct()->pluck('action')->sort()->values();
    }

    public function totalCount(array $dateFilters): int
    {
        return $this->baseQuery($dateFilters)->count();
    }

    public function countsByAction(array $dateFilters): Collection
    {
        return $this->baseQuery($dateFilters)
            ->groupBy('action')
            ->selectRaw('action, count(*) as count')
            ->get()
            ->pluck('count', 'action');
    }

    public function countsByUser(array $dateFilters): Collection
    {
        return $this->baseQuery($dateFilters)
            ->with('user:id,username')
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as count')
            ->get();
    }

    private function baseQuery(array $dateFilters)
    {
        $query = ActivityLog::query();
        $this->excludeSuperAdmin($query);
        $this->applyDateFilters($query, $dateFilters);

        return $query;
    }

    private function excludeSuperAdmin(Builder $query): void
    {
        $superAdminIds = User::role('SuperAdmin')->pluck('id');

        if ($superAdminIds->isNotEmpty()) {
            $query->whereNotIn('user_id', $superAdminIds);
        }
    }

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }
        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        $this->applyDateFilters($query, $filters);
    }

    private function applyDateFilters($query, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->where('performed_at', '>=', $filters['from'] . ' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('performed_at', '<=', $filters['to'] . ' 23:59:59');
        }
    }
}