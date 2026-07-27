<?php

namespace App\Services\Organization;

use App\Models\Division;
use App\Repositories\Organization\DivisionRepository;
use Illuminate\Support\Collection;

class DivisionService
{
    public function __construct(
        private readonly DivisionRepository $divisions,
    ) {
    }

    public function index(): Collection
    {
        return $this->divisions->allOrderedByName();
    }

    public function options(): Collection
    {
        return $this->divisions->options();
    }

    public function create(array $validated): Division
    {
        return $this->divisions->create($validated);
    }

    public function show(Division $division): Division
    {
        return $this->divisions->findWithRelations($division);
    }

    public function update(Division $division, array $validated): Division
    {
        return $this->divisions->update($division, $validated);
    }

    public function delete(Division $division): void
    {
        $this->divisions->reparentChildrenTo((int) $division->id, $division->parent_id);
        $this->divisions->nullifyDepartmentsFor((int) $division->id);
        $this->divisions->nullifyEmployeesFor((int) $division->id);
        $this->divisions->delete($division);
    }
}
