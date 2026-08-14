<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\ActOnApprovalStepRequest;
use App\Http\Resources\Leave\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Services\Leave\LeaveApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeaveApprovalController extends Controller
{
    public function __construct(private readonly LeaveApprovalService $approvalService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:leave.approval.view|leave.approval.manage')->only(['pending', 'recentDecisions']);
        $this->middleware('permission:leave.approval.manage')->only(['act', 'bulkApprove']);
    }

    public function pending(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $userRoles = $request->user()->getRoleNames()->all();

        $query = LeaveRequest::query()
            ->where('status', 'pending')
            ->whereHas('approvalSteps', function ($q) use ($request, $userRoles) {
                $q->whereColumn('leave_request_approval_steps.step_order', 'leave_requests.current_step_order')
                    ->where('status', 'pending')
                    ->where(function ($sub) use ($request, $userRoles) {
                        $sub->where('approver_id', $request->user()->id)
                            ->orWhere(function ($roleSub) use ($userRoles) {
                                $roleSub->whereNotNull('approver_role')
                                    ->whereIn('approver_role', $userRoles);
                            });
                    });
            })
            ->visibleTo($request->user())
            ->with(['leaveType', 'employee.primaryDepartmentAssignment.department', 'approvalSteps.approver'])
            ->latest('submitted_at');

        return LeaveRequestResource::collection($query->paginate($request->integer('per_page', 15)))
            ->response();
    }

    public function recentDecisions(Request $request): JsonResponse
    {
        $query = LeaveRequest::query()
            ->whereHas('approvalSteps', fn ($q) => $q->where('acted_by', $request->user()->id))
            ->whereIn('status', ['approved', 'rejected'])
            ->with([
                'leaveType',
                'employee.primaryDepartmentAssignment.department',
                'approvalSteps' => fn ($q) => $q->where('acted_by', $request->user()->id),
            ])
            ->latest('finalized_at')
            ->limit($request->integer('limit', 5));

        return response()->json(['data' => LeaveRequestResource::collection($query->get())]);
    }

    public function act(ActOnApprovalStepRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('act', $leaveRequest);

        try {
            $updated = $request->input('action') === 'approved'
                ? $this->approvalService->approveStep($leaveRequest, $request->user(), $request->input('remarks'))
                : $this->approvalService->rejectStep($leaveRequest, $request->user(), $request->input('remarks'));
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['action' => $e->getMessage()]);
        }

        return response()->json([
            'data' => new LeaveRequestResource(
                $updated->load(['leaveType', 'employee.primaryDepartmentAssignment.department', 'approvalSteps.approver'])
            ),
            'message' => $request->input('action') === 'approved' ? 'Request approved.' : 'Request rejected.',
        ]);
    }

    public function bulkApprove(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);
        abort_unless($request->user()->can('leave.approval.manage'), 403);

        $request->validate([
            'leave_request_ids' => ['required', 'array', 'min:1', 'max:50'],
            'leave_request_ids.*' => ['integer', 'exists:leave_requests,id'],
        ]);

        $result = $this->approvalService->bulkApprove($request->input('leave_request_ids'), $request->user());

        return response()->json([
            'approved_count' => count($result['approved']),
            'approved_ids' => $result['approved'],
            'skipped' => $result['skipped'],
        ]);
    }
}