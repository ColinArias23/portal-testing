<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:leave.types.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = LeaveType::query()->active();

        $isSuperAdmin = $request->user()->hasRole('SuperAdmin');

        $wantsFullCatalog = $isSuperAdmin
            || ($request->boolean('all')
                && ($request->user()->can('leave.balance.view') || $request->user()->can('leave.balance.manage')));

        if ($wantsFullCatalog) {
            $types = $query->orderBy('sort_order')->get([
                'id', 'code', 'name', 'color', 'bg_color', 'requires_date_range',
                'is_confidential', 'max_days', 'is_accrual_based', 'eligible_employment_types',
            ]);

            return response()->json(['data' => $types]);
        }

        $employee = $request->user()->employee;

        $types = $query
            ->eligibleFor($employee?->employment_type)
            ->orderBy('sort_order')
            ->get([
                'id', 'code', 'name', 'color', 'bg_color', 'requires_date_range',
                'is_confidential', 'max_days', 'is_accrual_based',
            ]);

        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $leaveType = LeaveType::create($data);

        return response()->json([
            'data' => $leaveType,
            'message' => 'Leave type created successfully.',
        ], 201);
    }

    public function update(Request $request, LeaveType $leaveType): JsonResponse
    {
        $data = $this->validated($request, $leaveType);

        $leaveType->update($data);

        return response()->json([
            'data' => $leaveType->fresh(),
            'message' => 'Leave type updated successfully.',
        ]);
    }

    public function destroy(LeaveType $leaveType): JsonResponse
    {
        if ($leaveType->leaveRequests()->exists()) {
            return response()->json([
                'message' => 'This leave type has existing leave requests and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        $leaveType->delete();

        return response()->json(['message' => 'Leave type deleted successfully.']);
    }

    private function validated(Request $request, ?LeaveType $leaveType = null): array
    {
        $codeRule = Rule::unique('leave_types', 'code');
        if ($leaveType) {
            $codeRule = $codeRule->ignore($leaveType->id);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:50', $codeRule],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'bg_color' => ['nullable', 'string', 'max:20'],
            'requires_date_range' => ['sometimes', 'boolean'],
            'is_confidential' => ['sometimes', 'boolean'],
            'max_days' => ['nullable', 'integer', 'min:0'],
            'is_accrual_based' => ['sometimes', 'boolean'],
            'default_monthly_accrual' => ['nullable', 'numeric', 'min:0'],
            'requires_balance_check' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'eligible_employment_types' => ['nullable', 'array'],
            'eligible_employment_types.*' => ['string', 'max:50'],
        ]);
    }
}