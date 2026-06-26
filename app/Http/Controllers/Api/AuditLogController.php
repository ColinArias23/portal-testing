<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:SuperAdmin|HR');
    }

    /**
     * Get paginated audit logs with filtering options
     */
    public function index(Request $request)
    {
        $request->validate([
            'action' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string'],
            'subject_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ActivityLog::with('user:id,username')
            ->orderByDesc('performed_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('from')) {
            $query->where('performed_at', '>=', $request->from . ' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('performed_at', '<=', $request->to . ' 23:59:59');
        }

        $logs = $query->paginate($request->per_page ?? 50);

        return response()->json($logs);
    }

    /**
     * Get available actions for filtering
     */
    public function getActions()
    {
        $actions = ActivityLog::distinct()
            ->pluck('action')
            ->sort()
            ->values();

        return response()->json([
            'actions' => $actions,
        ]);
    }

    /**
     * Get audit log summary
     */
    public function summary(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = ActivityLog::query();

        if ($request->filled('from')) {
            $query->where('performed_at', '>=', $request->from . ' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('performed_at', '<=', $request->to . ' 23:59:59');
        }

        $summary = [
            'total_actions' => $query->count(),
            'by_action' => $query->groupBy('action')
                ->selectRaw('action, count(*) as count')
                ->get()
                ->pluck('count', 'action'),
            'by_user' => $query->with('user:id,username')
                ->groupBy('user_id')
                ->selectRaw('user_id, count(*) as count')
                ->get()
                ->map(fn($log) => [
                    'user_id' => $log->user_id,
                    'username' => $log->user?->username,
                    'count' => $log->count,
                ]),
        ];

        return response()->json($summary);
    }
}
