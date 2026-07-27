<?php

namespace App\Services\RBAC;

use App\Repositories\RBAC\AuditLogRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditLogService
{
    public function __construct(
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    public function index(Request $request): LengthAwarePaginator
    {
        $filters = $request->only(['action', 'user_id', 'subject_type', 'subject_id', 'from', 'to']);

        return $this->auditLogs->paginate($filters, (int) ($request->per_page ?? 50));
    }

    public function getActions(): Collection
    {
        return $this->auditLogs->distinctActions();
    }

    public function summary(Request $request): array
    {
        $dateFilters = $request->only(['from', 'to']);

        return [
            'total_actions' => $this->auditLogs->totalCount($dateFilters),
            'by_action'     => $this->auditLogs->countsByAction($dateFilters),
            'by_user'       => $this->auditLogs->countsByUser($dateFilters)->map(fn ($log) => [
                'user_id'  => $log->user_id,
                'username' => $log->user?->username,
                'count'    => $log->count,
            ]),
        ];
    }
}
