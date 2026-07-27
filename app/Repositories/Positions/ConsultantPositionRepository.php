<?php

namespace App\Repositories\Positions;

use App\Models\ConsultantPosition;
use Illuminate\Pagination\LengthAwarePaginator;

class ConsultantPositionRepository
{
    public function paginate(?string $search, int $perPage): LengthAwarePaginator
    {
        $query = ConsultantPosition::query()->orderBy('title');

        if ($search) {
            $query->search($search);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data): ConsultantPosition
    {
        return ConsultantPosition::create($data);
    }

    public function update(ConsultantPosition $position, array $data): ConsultantPosition
    {
        $position->update($data);

        return $position->fresh();
    }

    public function delete(ConsultantPosition $position): void
    {
        $position->delete();
    }
}
