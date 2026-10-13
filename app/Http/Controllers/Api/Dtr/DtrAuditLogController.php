<?php

namespace App\Http\Controllers\Api\Dtr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Dtr\DtrAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DtrAuditLogController extends Controller
{
    public function __construct(private readonly DtrAuditLogService $auditLogs)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope'    => ['nullable', Rule::in(array_keys(DtrAuditLogService::SCOPES))],
            'action'   => ['nullable', 'string', 'max:100'],
            'user_id'  => ['nullable', 'integer'],
            'q'        => ['nullable', 'string', 'max:100'],
            'from'     => ['nullable', 'date_format:Y-m-d'],
            'to'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);

        $page = $this->auditLogs->paginate($data, (int) ($data['per_page'] ?? 25));

        return response()->json($page->through(fn (ActivityLog $log) => $this->present($log)));
    }

    private function present(ActivityLog $log): array
    {
        return [
            'id'            => $log->id,
            'action'        => strtolower($log->action),
            'performed_at'  => $log->performed_at?->toIso8601String(),
            'actor'         => $log->user ? [
                'id'       => $log->user->id,
                'username' => $log->user->username,
                'name'     => $log->user->employee?->full_name,
            ] : null,
            'subject_label' => $log->subject_label,
            'payload'       => $log->payload,
            'ip_address'    => $log->ip_address,
        ];
    }
}