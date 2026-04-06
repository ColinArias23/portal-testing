<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user:id,email')
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
            $query->where('performed_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('performed_at', '<=', $request->to);
        }

        $logs = $query->paginate($request->per_page ?? 50);

        return response()->json($logs);
    }
}