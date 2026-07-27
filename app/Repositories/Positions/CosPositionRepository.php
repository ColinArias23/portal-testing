<?php

namespace App\Repositories\Positions;

use App\Models\CosPosition;
use Illuminate\Pagination\LengthAwarePaginator;

class CosPositionRepository
{
    public function paginate(?string $search, int $perPage): LengthAwarePaginator
    {
        $query = CosPosition::query()->orderBy('title');

        if ($search) {
            $query->search($search);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data): CosPosition
    {
        return CosPosition::create($data);
    }

    public function update(CosPosition $position, array $data): CosPosition
    {
        $position->update($data);

        return $position->fresh();
    }

    public function delete(CosPosition $position): void
    {
        $position->delete();
    }
}
