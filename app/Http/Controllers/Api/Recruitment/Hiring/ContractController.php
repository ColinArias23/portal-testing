<?php

namespace App\Http\Controllers\Api\Recruitment\Hiring;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\Recruitment\Hiring\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractService $contracts,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:renewals.view')->only(['index']);
        $this->middleware('permission:renewals.manage')->only(['store', 'update', 'destroy', 'updateAutoRenewal', 'manualRenew']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        return response()->json($this->contracts->index());
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'   => ['nullable', 'exists:employees,id'],
            'applicant_id'  => ['nullable', 'exists:applicants,id'],
            'employee_name' => ['required', 'string', 'max:150'],
            'position'      => ['nullable', 'string', 'max:100'],
            'salary'        => ['nullable', 'numeric', 'min:0'],
            'start_date'    => ['nullable', 'date'],
            'end_date'      => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'        => ['nullable', 'string', 'max:30'],
        ]);

        return response()->json($this->contracts->store($request, $data), 201);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Contract $contract): JsonResponse
    {
        $data = $request->validate([
            'employee_id'   => ['nullable', 'exists:employees,id'],
            'applicant_id'  => ['nullable', 'exists:applicants,id'],
            'employee_name' => ['sometimes', 'string', 'max:150'],
            'position'      => ['nullable', 'string', 'max:100'],
            'salary'        => ['nullable', 'numeric', 'min:0'],
            'start_date'    => ['nullable', 'date'],
            'end_date'      => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'        => ['sometimes', 'nullable', 'string', 'in:Draft,Generated,Signed', 'max:30'],
        ]);

        return response()->json($this->contracts->update($request, $contract, $data));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Contract $contract): JsonResponse
    {
        $this->contracts->destroy($request, $contract);

        return response()->json(['message' => 'Contract deleted successfully.']);
    }

    // ─── UpdateAutoRenewal ─────────────────────────────────────────────────────
    public function updateAutoRenewal(Request $request, Contract $contract): JsonResponse
    {
        $data = $request->validate([
            'auto_renewal_enabled'         => ['required', 'boolean'],
            'auto_renewal_duration_months' => ['sometimes', 'integer', Rule::in([6])],
            'auto_renewal_month'           => ['sometimes', 'nullable', Rule::in(['JUNE', 'DECEMBER'])],
        ]);

        return response()->json([
            'message' => 'Auto-renewal settings updated.',
            'data'    => $this->contracts->updateAutoRenewal($request, $contract, $data),
        ]);
    }

    // ─── ManualRenew ─────────────────────────────────────────────────────
    public function manualRenew(Request $request, Contract $contract): JsonResponse
    {
        $result = $this->contracts->manualRenew($request, $contract);

        return response()->json($result['body'], $result['status']);
    }
}
