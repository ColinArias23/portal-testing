<?php

namespace App\Services\Leave;

use App\Enums\LeaveBalanceTransactionType;
use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveBalanceService
{
    private function resolveChargeableType(LeaveType $leaveType): LeaveType
    {
        if ($leaveType->code !== 'mandatory_forced') {
            return $leaveType;
        }

        return LeaveType::where('code', 'vacation')->firstOrFail();
    }

    public function getOrCreate(Employee $employee, LeaveType $leaveType, int $year, bool $forUpdate = false): LeaveBalance
    {
        try {
            $balance = LeaveBalance::query()->firstOrCreate([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $balance = LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->first();

            if (! $balance) {
                throw new RuntimeException(
                    "Failed to resolve leave balance for employee {$employee->id}, ".
                    "leave type {$leaveType->id}, year {$year} after a duplicate-key race."
                );
            }
        }

        if ($forUpdate) {
            $balance = LeaveBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }

        if ($this->isAnnualGrantDue($balance, $leaveType)) {
            $balance = $this->applyAnnualGrant($balance, $leaveType, $year);
        }

        return $balance;
    }

    private function isAnnualGrantDue(LeaveBalance $balance, LeaveType $leaveType): bool
    {
        $grantDays = (float) ($leaveType->annual_grant_days ?? 0);

        return $grantDays > 0 && (float) $balance->earned < $grantDays;
    }

    private function applyAnnualGrant(LeaveBalance $balance, LeaveType $leaveType, int $year): LeaveBalance
    {
        return DB::transaction(function () use ($balance, $leaveType, $year) {
            $locked = LeaveBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();

            $grantDays = (float) $leaveType->annual_grant_days;
            $currentEarned = (float) $locked->earned;

            if ($currentEarned >= $grantDays) {
                return $locked;
            }

            $delta = $grantDays - $currentEarned;
            $locked->update(['earned' => $grantDays]);
            $locked->refresh();

            LeaveBalanceTransaction::create([
                'leave_balance_id' => $locked->id,
                'type' => LeaveBalanceTransactionType::Accrual,
                'amount' => $delta,
                'balance_after' => $locked->available,
                'remarks' => "Automatic {$year} annual entitlement grant ({$leaveType->name})",
                'created_by' => null,
            ]);

            return $locked;
        });
    }

    public function available(Employee $employee, LeaveType $leaveType, int $year): float
    {
        $balance = $this->getOrCreate($employee, $this->resolveChargeableType($leaveType), $year);

        return (float) $balance->available;
    }

    public function debitForApproval(LeaveRequest $leaveRequest): void
    {
        if (! $leaveRequest->leaveType->requires_balance_check) {
            return;
        }

        if ($leaveRequest->leaveType->code === 'monetization') {
            $credits = (float) ($leaveRequest->details['creditsToMonetize'] ?? 0);
            if ($credits <= 0) {
                throw new RuntimeException('Monetization request is missing a valid credits_to_monetize amount.');
            }

            $year = $leaveRequest->created_at->year;
            $this->monetize(
                $leaveRequest->employee,
                $leaveRequest->leaveType,
                $credits,
                $year,
                $leaveRequest,
                "Monetized via approved request {$leaveRequest->request_number}"
            );

            return;
        }

        if (! $leaveRequest->total_days) {
            return;
        }

        DB::transaction(function () use ($leaveRequest) {
            $year = $leaveRequest->start_date?->year ?? now()->year;
            $chargeableType = $this->resolveChargeableType($leaveRequest->leaveType);
            $balance = $this->getOrCreate($leaveRequest->employee, $chargeableType, $year, forUpdate: true);

            if ((float) $balance->available < (float) $leaveRequest->total_days) {
                throw new RuntimeException(
                    "Insufficient {$chargeableType->name} balance: available "
                    . number_format($balance->available, 2) . ', requested '
                    . number_format($leaveRequest->total_days, 2) . '.'
                );
            }

            $balance->increment('used', $leaveRequest->total_days);
            $balance->refresh();

            $remarks = $chargeableType->is($leaveRequest->leaveType)
                ? "Debited for approved request {$leaveRequest->request_number}"
                : "Debited for approved {$leaveRequest->leaveType->name} request {$leaveRequest->request_number} (charged to {$chargeableType->name})";

            LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'leave_request_id' => $leaveRequest->id,
                'type' => LeaveBalanceTransactionType::Usage,
                'amount' => -1 * $leaveRequest->total_days,
                'balance_after' => $balance->available,
                'remarks' => $remarks,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function restore(LeaveRequest $leaveRequest, string $reason): void
    {
        if (! $leaveRequest->leaveType->requires_balance_check || ! $leaveRequest->total_days) {
            return;
        }

        DB::transaction(function () use ($leaveRequest, $reason) {
            $year = $leaveRequest->start_date?->year ?? now()->year;
            $chargeableType = $this->resolveChargeableType($leaveRequest->leaveType);
            $balance = $this->getOrCreate($leaveRequest->employee, $chargeableType, $year, forUpdate: true);

            $decrementBy = min((float) $leaveRequest->total_days, (float) $balance->used);

            $balance->decrement('used', $decrementBy);
            $balance->refresh();

            LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'leave_request_id' => $leaveRequest->id,
                'type' => LeaveBalanceTransactionType::Restoration,
                'amount' => $decrementBy,
                'balance_after' => $balance->available,
                'remarks' => $reason,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function monetize(Employee $employee, LeaveType $leaveType, float $credits, int $year, ?LeaveRequest $leaveRequest, string $remarks): void
    {
        DB::transaction(function () use ($employee, $leaveType, $credits, $year, $leaveRequest, $remarks) {
            $balance = $this->getOrCreate($employee, $leaveType, $year, forUpdate: true);

            if ((float) $balance->available < $credits) {
                throw new RuntimeException('Insufficient balance to monetize the requested credits.');
            }

            $balance->increment('monetized', $credits);
            $balance->refresh();

            LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'leave_request_id' => $leaveRequest?->id,
                'type' => LeaveBalanceTransactionType::Monetization,
                'amount' => -1 * $credits,
                'balance_after' => $balance->available,
                'remarks' => $remarks,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function adjust(Employee $employee, LeaveType $leaveType, int $year, float $signedAmount, string $remarks): LeaveBalance
    {
        return DB::transaction(function () use ($employee, $leaveType, $year, $signedAmount, $remarks) {
            $balance = $this->getOrCreate($employee, $leaveType, $year, forUpdate: true);

            $balance->increment('adjusted', $signedAmount);
            $balance->refresh();

            LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'type' => LeaveBalanceTransactionType::Adjustment,
                'amount' => $signedAmount,
                'balance_after' => $balance->available,
                'remarks' => $remarks,
                'created_by' => auth()->id(),
            ]);

            return $balance;
        });
    }

    public function accrue(Employee $employee, LeaveType $leaveType, int $year, float $amount, string $remarks): void
    {
        DB::transaction(function () use ($employee, $leaveType, $year, $amount, $remarks) {
            $balance = $this->getOrCreate($employee, $leaveType, $year, forUpdate: true);

            $balance->increment('earned', $amount);
            $balance->refresh();

            LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'type' => LeaveBalanceTransactionType::Accrual,
                'amount' => $amount,
                'balance_after' => $balance->available,
                'remarks' => $remarks,
                'created_by' => null,
            ]);
        });
    }

    public function mandatoryForcedStatus(Employee $employee, int $year): array
    {
        $mandatoryType = LeaveType::where('code', 'mandatory_forced')->first();
        $requiredDays = (float) ($mandatoryType?->max_days ?? 5);

        $usedDays = (float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereHas('leaveType', fn ($q) => $q->where('code', 'mandatory_forced'))
            ->whereIn('status', [
                LeaveRequestStatus::Pending,
                LeaveRequestStatus::ReturnedForRevision,
                LeaveRequestStatus::Approved,
            ])
            ->whereYear('start_date', $year)
            ->sum('total_days');

        $remainingDays = max(0, $requiredDays - $usedDays);

        return [
            'required_days' => $requiredDays,
            'used_days' => $usedDays,
            'remaining_days' => $remainingDays,
            'is_fulfilled' => $remainingDays <= 0,
        ];
    }

    public function entitlementUsageStatus(Employee $employee, LeaveType $leaveType, int $year): array
    {
        $capDays = (float) ($leaveType->max_days ?? 0);

        $usedDays = (float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereIn('status', [
                LeaveRequestStatus::Pending,
                LeaveRequestStatus::ReturnedForRevision,
                LeaveRequestStatus::Approved,
            ])
            ->whereYear('start_date', $year)
            ->sum('total_days');

        $remainingDays = max(0, $capDays - $usedDays);

        return [
            'annual_cap_days' => $capDays,
            'used_days' => $usedDays,
            'remaining_days' => $remainingDays,
            'is_exhausted' => $remainingDays <= 0,
        ];
    }

    public function setCredits(Employee $employee, LeaveType $leaveType, int $year, array $values, string $remarks, ?int $actorId = null): LeaveBalance
    {
        return DB::transaction(function () use ($employee, $leaveType, $year, $values, $remarks, $actorId) {
            $balance = $this->getOrCreate($employee, $leaveType, $year, forUpdate: true);
            $beforeAvailable = (float) $balance->available;

            $balance->update(array_intersect_key(
                $values,
                array_flip(['opening_balance', 'carry_forward_in', 'used'])
            ));
            $balance->refresh();

            $delta = (float) $balance->available - $beforeAvailable;

            LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'type' => LeaveBalanceTransactionType::Adjustment,
                'amount' => $delta,
                'balance_after' => $balance->available,
                'remarks' => $remarks,
                'created_by' => $actorId,
            ]);

            return $balance;
        });
    }

    public function resetCredits(Employee $employee, LeaveType $leaveType, int $year, ?int $actorId = null): LeaveBalance
    {
        return $this->setCredits(
            $employee,
            $leaveType,
            $year,
            ['opening_balance' => 0, 'carry_forward_in' => 0, 'used' => 0],
            "Credits reset to zero by admin",
            $actorId,
        );
    }
}