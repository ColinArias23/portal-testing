<?php

namespace App\Services\Dtr;

use App\Repositories\RBAC\AuditLogRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class DtrAuditLogService
{
    public const SCOPES = [
        'attendance' => 'dtr.attendance.',
        'shift'      => 'dtr.shift.',
        'face'       => 'dtr.face.',
    ];

    private const ALL_DTR_PREFIX = 'dtr.';

    public function __construct(
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $prefix = self::SCOPES[$filters['scope'] ?? ''] ?? self::ALL_DTR_PREFIX;

        if (! empty($filters['action'])) {
            $filters['action'] = strtoupper($filters['action']);
        }

        return $this->auditLogs->paginateByActionPrefix($prefix, $filters, $perPage);
    }
}