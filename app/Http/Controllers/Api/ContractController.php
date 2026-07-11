<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Notifications\ContractAutoRenewed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:renewals.view')->only(['index']);
        $this->middleware('permission:renewals.manage')->only(['store', 'update', 'destroy', 'updateAutoRenewal', 'manualRenew']);
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        return response()->json(
            Contract::with(['applicant', 'employee'])->orderBy('created_at', 'desc')->get()
        );
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

        $contract = Contract::create($data);

        ActivityLog::record(
            action:       'contract.created',
            userId:       $request->user()?->id,
            subjectType:  Contract::class,
            subjectId:    $contract->id,
            subjectLabel: $contract->employee_name,
            payload:      [
                'position'   => $contract->position,
                'salary'     => $contract->salary,
                'start_date' => $contract->start_date?->toDateTimeString(),
                'end_date'   => $contract->end_date?->toDateString(),
                'status'     => $contract->status,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($contract->load(['applicant', 'employee']), 201);
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

        $before = [
            'applicant_id' => $contract->applicant_id,
            'employee_id'  => $contract->employee_id,
            'status'       => $contract->status,
            'salary'       => $contract->salary,
            'position'     => $contract->position,
            'start_date'   => $contract->start_date?->toDateTimeString(),
            'end_date'     => $contract->end_date?->toDateString(),
        ];

        if ($contract->status === 'Signed' && ! $request->has('status')) {
            unset($data['status']);
        }

        $contract->update($data);

        ActivityLog::record(
            action:       'contract.updated',
            userId:       $request->user()?->id,
            subjectType:  Contract::class,
            subjectId:    $contract->id,
            subjectLabel: $contract->employee_name,
            payload:      [
                'before' => $before,
                'after'  => [
                    'applicant_id' => $contract->applicant_id,
                    'employee_id'  => $contract->employee_id,
                    'status'       => $contract->status,
                    'salary'       => $contract->salary,
                    'position'     => $contract->position,
                    'start_date'   => $contract->start_date?->toDateTimeString(),
                    'end_date'     => $contract->end_date?->toDateString(),
                ],
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($contract->fresh()->load(['applicant', 'employee']));
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Contract $contract): JsonResponse
    {
        $id    = $contract->id;
        $label = $contract->employee_name;

        $contract->delete();

        ActivityLog::record(
            action:       'contract.deleted',
            userId:       $request->user()?->id,
            subjectType:  Contract::class,
            subjectId:    $id,
            subjectLabel: $label,
            payload:      [
                'position' => $contract->position,
                'status'   => $contract->status,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

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

        $before = [
            'auto_renewal_enabled'         => $contract->auto_renewal_enabled,
            'auto_renewal_duration_months' => $contract->auto_renewal_duration_months,
            'auto_renewal_month'           => $contract->auto_renewal_month,
        ];

        $contract->update($data);

        ActivityLog::record(
            action:       'contract.auto_renewal_updated',
            userId:       $request->user()?->id,
            subjectType:  Contract::class,
            subjectId:    $contract->id,
            subjectLabel: $contract->employee_name,
            payload:      ['before' => $before, 'after' => $data],
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );

        return response()->json([
            'message' => 'Auto-renewal settings updated.',
            'data'    => $contract->fresh()->load(['applicant', 'employee']),
        ]);
    }
// ─── ManualRenew ─────────────────────────────────────────────────────
    public function manualRenew(Request $request, Contract $contract): JsonResponse
    {
        if (! $contract->auto_renewal_enabled) {
            return response()->json([
                'message' => 'Auto-renewal is not enabled for this contract.',
            ], 422);
        }

        $renewed = DB::transaction(function () use ($contract): Contract {
            $contract->update([
                'status'               => 'Expired',
                'last_auto_renewed_at' => now(),
            ]);

            $renewal = $contract->buildRenewal();
            $renewal->save();

            $notifiable = $contract->employee;
            if ($notifiable !== null) {
                $notifiable->notify(new ContractAutoRenewed($renewal));
            }

            return $renewal;
        });

        ActivityLog::record(
            action:       'contract.manual_renewed',
            userId:       $request->user()?->id,
            subjectType:  Contract::class,
            subjectId:    $renewed->id,
            subjectLabel: $renewed->employee_name,
            payload:      [
                'parent_contract_id' => $contract->id,
                'renewal_generation' => $renewed->renewal_generation,
                'new_start'          => $renewed->start_date?->toDateString(),
                'new_end'            => $renewed->end_date?->toDateString(),
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'message' => 'Contract renewed successfully.',
            'data'    => $renewed->load(['applicant', 'employee']),
        ], 201);
    }
}