<?php

namespace App\Services\Positions;

use App\Models\CosPosition;
use App\Repositories\Positions\CosPositionRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CosPositionService
{
    public function __construct(
        private readonly CosPositionRepository $positions,
    ) {
    }

    public function index(Request $request): LengthAwarePaginator
    {
        return $this->positions->paginate(
            $request->input('search'),
            (int) $request->input('per_page', 15)
        );
    }

    public function store(array $data): CosPosition
    {
        return $this->positions->create($data);
    }

    public function update(CosPosition $position, array $data): CosPosition
    {
        return $this->positions->update($position, $data);
    }

    public function destroy(CosPosition $position): void
    {
        $this->positions->delete($position);
    }
}
