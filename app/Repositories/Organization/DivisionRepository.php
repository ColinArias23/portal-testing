<?php

namespace App\Repositories\Organization;

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;

class DivisionRepository
{
    private const RELATIONS = ['departments', 'head', 'parent', 'children'];

    public function allOrderedByName(): Collection
    {
        return Division::with(self::RELATIONS)->orderBy('name')->get();
    }

    public function options(): Collection
    {
        return Division::orderBy('name')->get(['id', 'name']);
    }

    public function create(array $attributes): Division
    {
        $division = Division::create($attributes);
        $division->load(self::RELATIONS);

        return $division;
    }

    public function findWithRelations(Division $division): Division
    {
        return $division->load(self::RELATIONS);
    }

    public function update(Division $division, array $attributes): Division
    {
        $division->update($attributes);

        return $division->fresh(self::RELATIONS);
    }

    public function reparentChildrenTo(int $divisionId, ?int $newParentId): void
    {
        Division::where('parent_id', $divisionId)->update(['parent_id' => $newParentId]);
    }

    public function nullifyDepartmentsFor(int $divisionId): void
    {
        Department::where('division_id', $divisionId)->update(['division_id' => null]);
    }

    public function nullifyEmployeesFor(int $divisionId): void
    {
        Employee::where('division_id', $divisionId)->update(['division_id' => null]);
    }

    public function delete(Division $division): void
    {
        $division->delete();
    }
}
