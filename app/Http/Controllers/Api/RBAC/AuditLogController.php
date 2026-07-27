<?php

namespace App\Http\Controllers\Api\RBAC;

use App\Http\Controllers\Controller;
use App\Services\RBAC\AuditLogService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogs,
    ) {
        $this->middleware('permission:audit_logs.view');
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $request->validate([
            'action'       => ['nullable', 'string'],
            'user_id'      => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string'],
            'subject_id'   => ['nullable', 'integer'],
            'from'         => ['nullable', 'date'],
            'to'           => ['nullable', 'date'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->auditLogs->index($request));
    }

    // ─── GetActions ─────────────────────────────────────────────────────
    public function getActions()
    {
        return response()->json(['actions' => $this->auditLogs->getActions()]);
    }

    // ─── Summary ─────────────────────────────────────────────────────
    public function summary(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        return response()->json($this->auditLogs->summary($request));
    }
}
