<?php

namespace App\Repositories\Recruitment\Hiring;

use App\Models\Contract;
use Illuminate\Support\Collection;

class ContractRepository
{
    private const RELATIONS = ['applicant', 'employee'];

    public function allOrderedByCreated(): Collection
    {
        return Contract::with(self::RELATIONS)->orderByDesc('created_at')->get();
    }

    public function create(array $data): Contract
    {
        return Contract::create($data)->load(self::RELATIONS);
    }

    public function update(Contract $contract, array $data): Contract
    {
        $contract->update($data);

        return $contract->fresh()->load(self::RELATIONS);
    }

    public function delete(Contract $contract): void
    {
        $contract->delete();
    }
}
