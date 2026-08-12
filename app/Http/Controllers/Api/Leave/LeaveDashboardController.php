<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:leave.dashboard.view')->only(['overview', 'activity']);
    }

    public function personal(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'data' => [
                    'pending_count' => 0,
                    'approved_count' => 0,
                    'rejected_count' => 0,
                    'balances' => [],
                    'upcoming_holidays' => \App\Models\Holiday::upcoming()->limit(5)->get(['id', 'name', 'date', 'type']),
                ],
            ]);
        }

        $year = $request->integer('year', now()->year);

        $counts = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereYear('created_at', $year)
            ->selectRaw("status, count(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $balances = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->with('leaveType')
            ->get()
            ->map(fn (LeaveBalance $b) => [
                'leave_type' => $b->leaveType->code,
                'label' => $b->leaveType->name,
                'available' => (float) $b->available,
                'used' => (float) $b->used,
            ]);

        return response()->json([
            'data' => [
                'pending_count' => (int) ($counts['pending'] ?? 0),
                'approved_count' => (int) ($counts['approved'] ?? 0),
                'rejected_count' => (int) ($counts['rejected'] ?? 0),
                'balances' => $balances,
                'upcoming_holidays' => \App\Models\Holiday::upcoming()->limit(5)->get(['id', 'name', 'date', 'type']),
            ],
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 5), 20);

        $requests = LeaveRequest::query()
            ->visibleTo($request->user())
            ->with(['employee:id,first_name,middle_name,last_name,suffix,prefix', 'leaveType:id,name'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LeaveRequest $leaveRequest) => [
                'id' => $leaveRequest->id,
                'employee' => $leaveRequest->employee?->full_name ?? '—',
                'leaveType' => $leaveRequest->leaveType?->name ?? '—',
                'startDate' => optional($leaveRequest->start_date)->toDateString(),
                'endDate' => optional($leaveRequest->end_date)->toDateString(),
                'status' => $leaveRequest->status?->label() ?? '—',
            ]);

        return response()->json(['data' => $requests]);
    }

    public function overview(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('leave.dashboard.view'), 403);

        $year = $request->integer('year', now()->year);

        $statusCounts = LeaveRequest::query()
            ->whereYear('created_at', $year)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $monthlyTrends = LeaveRequest::query()
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereYear('leave_requests.start_date', $year)
            ->where('leave_requests.status', 'approved')
            ->selectRaw('MONTH(leave_requests.start_date) as month, leave_types.code as leave_type, SUM(leave_requests.total_days) as days')
            ->groupBy('month', 'leave_type')
            ->get()
            ->groupBy('month')
            ->map(fn ($rows, $month) => [
                'month' => Carbon::create($year, (int) $month, 1)->format('M'),
                'breakdown' => $rows->pluck('days', 'leave_type'),
            ])
            ->values();

        $deptUsage = LeaveRequest::query()
            ->join('employee_department_assignments', function ($join) {
                $join->on('employee_department_assignments.employee_id', '=', 'leave_requests.employee_id')
                    ->where('employee_department_assignments.is_primary', true)
                    ->whereNull('employee_department_assignments.end_date');
            })
            ->join('departments', 'departments.id', '=', 'employee_department_assignments.department_id')
            ->whereYear('leave_requests.start_date', $year)
            ->where('leave_requests.status', 'approved')
            ->selectRaw('departments.name as dept, SUM(leave_requests.total_days) as days')
            ->groupBy('departments.name')
            ->orderByDesc('days')
            ->get();

        $typeDistribution = LeaveRequest::query()
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereYear('leave_requests.start_date', $year)
            ->where('leave_requests.status', 'approved')
            ->selectRaw('leave_types.name, leave_types.color, SUM(leave_requests.total_days) as value')
            ->groupBy('leave_types.name', 'leave_types.color')
            ->get();

        return response()->json([
            'data' => [
                'pending_count' => (int) ($statusCounts['pending'] ?? 0),
                'approved_count' => (int) ($statusCounts['approved'] ?? 0),
                'rejected_count' => (int) ($statusCounts['rejected'] ?? 0),
                'monthly_trends' => $monthlyTrends,
                'department_usage' => $deptUsage,
                'leave_type_distribution' => $typeDistribution,
            ],
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('leave.dashboard.view'), 403);

        $logs = \App\Models\LeaveRequestLog::query()
            ->with(['actor', 'leaveRequest.employee', 'leaveRequest.leaveType'])
            ->whereHas('leaveRequest', fn ($q) => $q->where('is_confidential', false))
            ->whereIn('action', ['submitted', 'finalized_approved', 'finalized_rejected'])
            ->latest('created_at')
            ->limit($request->integer('limit', 10))
            ->get();

        return response()->json(['data' => $logs]);
    }
}