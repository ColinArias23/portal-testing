<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    private const LIST_COLUMNS = [
        'id', 'code', 'name', 'description', 'filing_guidelines', 'required_documents',
        'color', 'bg_color', 'unit', 'requires_date_range',
        'is_confidential', 'max_days', 'enforce_annual_cap', 'is_accrual_based', 'requires_balance_check',
    ];

    public function __construct(private readonly LeaveBalanceService $balanceService)
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
                ...self::LIST_COLUMNS,
                'eligible_employment_types',
                'eligible_genders',
            ])->append(['category', 'category_label']);

            return response()->json(['data' => $types]);
        }

        $employee = $request->user()->employee;

        $types = $query
            ->eligibleFor($employee?->employment_type)
            ->eligibleForGender($employee?->info?->gender)
            ->orderBy('sort_order')
            ->get(self::LIST_COLUMNS)
            ->append(['category', 'category_label']);

        $types = $this->applyMandatoryForcedRule($types, $employee);
        $types = $this->annotateEntitlementCaps($types, $employee);

        return response()->json(['data' => $types]);
    }

    private function applyMandatoryForcedRule($types, ?Employee $employee)
    {
        if (! $employee) {
            return $types->filter(fn ($t) => $t->code !== 'mandatory_forced')->values();
        }

        $dueMonth = (int) config('leave.mandatory_forced.due_month', 12);
        $status = $this->balanceService->mandatoryForcedStatus($employee, now()->year);
        $isDue = now()->month >= $dueMonth && $status['remaining_days'] > 0;

        return $types
            ->filter(fn ($t) => $t->code !== 'mandatory_forced' || $isDue)
            ->map(function ($t) use ($isDue, $status) {
                if ($t->code === 'mandatory_forced' && $isDue) {
                    $t->filing_guidelines = [
                        'variant' => 'warning',
                        'title' => 'Mandatory Leave Requirement',
                        'message' => 'You still need to use '
                            . $this->formatDays($status['remaining_days'])
                            . ' more day(s) of Vacation Leave as Mandatory/Forced Leave before December 31, or it will be forfeited.',
                    ];
                }

                return $t;
            })
            ->values();
    }

    private function annotateEntitlementCaps($types, ?Employee $employee)
    {
        if (! $employee) {
            return $types;
        }

        return $types->map(function ($t) use ($employee) {
            if (! $t->enforce_annual_cap) {
                return $t;
            }

            $status = $this->balanceService->entitlementUsageStatus($employee, $t, now()->year);

            $t->entitlement_status = $status;
            $t->filing_guidelines = [
                'variant' => $status['is_exhausted'] ? 'warning' : 'info',
                'title' => 'Annual Entitlement',
                'message' => $status['is_exhausted']
                    ? "You've already used all {$this->formatDays($status['annual_cap_days'])} day(s) of {$t->name} for this year."
                    : "You have {$this->formatDays($status['remaining_days'])} of {$this->formatDays($status['annual_cap_days'])} day(s) of {$t->name} remaining this year.",
            ];

            return $t;
        })->values();
    }

    private function formatDays(float $days): string
    {
        return rtrim(rtrim(number_format($days, 2), '0'), '.');
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
            'unit' => ['nullable', 'string', 'in:days,hours'],
            'requires_date_range' => ['sometimes', 'boolean'],
            'is_confidential' => ['sometimes', 'boolean'],
            'max_days' => ['nullable', 'integer', 'min:0'],
            'enforce_annual_cap' => ['sometimes', 'boolean'],
            'is_accrual_based' => ['sometimes', 'boolean'],
            'default_monthly_accrual' => ['nullable', 'numeric', 'min:0'],
            'annual_grant_days' => ['nullable', 'numeric', 'min:0'],
            'requires_balance_check' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'eligible_employment_types' => ['nullable', 'array'],
            'eligible_employment_types.*' => ['string', 'max:50'],
            'eligible_genders' => ['nullable', 'array'],
            'eligible_genders.*' => ['string', 'in:MALE,FEMALE'],

            'filing_guidelines' => ['nullable', 'array'],
            'filing_guidelines.variant' => ['nullable', 'string', 'in:info,warning,confidential'],
            'filing_guidelines.title' => ['required_with:filing_guidelines', 'string', 'max:150'],
            'filing_guidelines.message' => ['required_with:filing_guidelines', 'string', 'max:1000'],

            'required_documents' => ['nullable', 'array'],
            'required_documents.*.id' => ['required', 'string', 'max:100'],
            'required_documents.*.label' => ['required', 'string', 'max:200'],
            'required_documents.*.required' => ['sometimes', 'boolean'],
        ]);
    }
}