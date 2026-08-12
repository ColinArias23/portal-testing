<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Support\Leave\DepartmentResolver;
use App\Support\Leave\PositionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveCalendarController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:leave.calendar.view');
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $leaves = LeaveRequest::query()
            ->visibleTo($request->user())
            ->where('status', 'approved')
            ->whereNotNull('start_date')
            ->where('start_date', '<=', $request->input('to'))
            ->where('end_date', '>=', $request->input('from'))
            ->with([
                'employee.primaryDepartmentAssignment.department',
                'employee.primaryAssignment.plantillaPosition',
                'employee.cosPosition',
                'employee.consultantPosition',
                'leaveType:id,code,name,color',
            ])
            ->get()
            ->map(function (LeaveRequest $r) use ($request) {
                $canSeeIdentity = ! $r->is_confidential
                    || $request->user()->can('leave.request.manage')
                    || $r->employee_id === $request->user()->employee_id;

                $department = $canSeeIdentity ? DepartmentResolver::forEmployee($r->employee) : null;
                $position = $canSeeIdentity ? PositionResolver::forEmployee($r->employee) : null;

                return [
                    'id' => $r->id,
                    'request_number' => $r->request_number,
                    'employee_name' => $canSeeIdentity ? $r->employee->full_name : 'Employee (confidential)',
                    'employee_first_name' => $canSeeIdentity ? $r->employee->first_name : null,
                    'employee_last_name' => $canSeeIdentity ? $r->employee->last_name : null,
                    'department' => $department?->name,
                    'employment_type' => $canSeeIdentity ? $r->employee->employment_type : null,
                    'position' => $position,
                    'leave_type' => $canSeeIdentity ? $r->leaveType->code : 'confidential',
                    'leave_type_name' => $canSeeIdentity ? $r->leaveType->name : 'Confidential',
                    'color' => $r->leaveType->color,
                    'start_date' => $r->start_date->toDateString(),
                    'end_date' => $r->end_date->toDateString(),
                    'status' => $r->status->value,
                    'is_confidential' => $r->is_confidential,
                ];
            });

        $holidays = Holiday::query()
            ->whereBetween('date', [$request->input('from'), $request->input('to')])
            ->get(['id', 'name', 'date', 'type']);

        return response()->json(['data' => ['leaves' => $leaves, 'holidays' => $holidays]]);
    }
}