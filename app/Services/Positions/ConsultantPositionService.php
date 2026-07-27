<?php

namespace App\Services\Positions;

use App\Models\ConsultantPosition;
use App\Repositories\Positions\ConsultantPositionRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ConsultantPositionService
{
    public function __construct(
        private readonly ConsultantPositionRepository $positions,
    ) {
    }

    public function index(Request $request): LengthAwarePaginator
    {
        return $this->positions->paginate(
            $request->input('search'),
            (int) $request->input('per_page', 15)
        );
    }

    public function store(array $data): ConsultantPosition
    {
        return $this->positions->create($data);
    }

    public function update(ConsultantPosition $position, array $data): ConsultantPosition
    {
        return $this->positions->update($position, $data);
    }

    public function destroy(ConsultantPosition $position): void
    {
        $this->positions->delete($position);
    }
}
