<?php

namespace App\Services\Recruitment\Hiring;

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Notifications\Recruitment\Hiring\ContractAutoRenewed;
use App\Repositories\Recruitment\Hiring\ContractRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContractService
{
    public function __construct(
        private readonly ContractRepository $contracts,
    ) {
    }

    public function index(): Collection
    {
        return $this->contracts->allOrderedByCreated();
    }

    public function store(Request $request, array $data): Contract
    {
        $contract = $this->contracts->create($data);

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

        return $contract;
    }

    public function update(Request $request, Contract $contract, array $data): Contract
    {
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

        $contract = $this->contracts->update($contract, $data);

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

        return $contract;
    }

    public function destroy(Request $request, Contract $contract): void
    {
        $id    = $contract->id;
        $label = $contract->employee_name;

        $this->contracts->delete($contract);

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
    }

    public function updateAutoRenewal(Request $request, Contract $contract, array $data): Contract
    {
        $before = [
            'auto_renewal_enabled'         => $contract->auto_renewal_enabled,
            'auto_renewal_duration_months' => $contract->auto_renewal_duration_months,
            'auto_renewal_month'           => $contract->auto_renewal_month,
        ];

        $contract = $this->contracts->update($contract, $data);

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

        return $contract;
    }

    /**
     * @return array{status:int, body:array}
     */
    public function manualRenew(Request $request, Contract $contract): array
    {
        if (! $contract->auto_renewal_enabled) {
            return [
                'status' => 422,
                'body' => ['message' => 'Auto-renewal is not enabled for this contract.'],
            ];
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

        return [
            'status' => 201,
            'body' => [
                'message' => 'Contract renewed successfully.',
                'data'    => $renewed->load(['applicant', 'employee']),
            ],
        ];
    }
}
