<?php

namespace App\Repositories\Organization;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

class DepartmentRepository
{
    private const RELATIONS = ['head', 'division'];
    private const FULL_RELATIONS = ['head', 'division', 'parent', 'parentDivision'];

    public function allOrderedByName(): Collection
    {
        return Department::with(self::RELATIONS)->orderBy('name')->get();
    }

    public function options(): Collection
    {
        return Department::orderBy('name')->get(['id', 'name']);
    }

    public function create(array $attributes): Department
    {
        $department = Department::create($attributes);
        $department->load(self::FULL_RELATIONS);

        return $department;
    }

    public function findWithBasicRelations(Department $department): Department
    {
        return $department->load(['head', 'division', 'parent']);
    }

    public function update(Department $department, array $attributes): Department
    {
        $department->update($attributes);

        return $department->fresh(self::FULL_RELATIONS);
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }
}
