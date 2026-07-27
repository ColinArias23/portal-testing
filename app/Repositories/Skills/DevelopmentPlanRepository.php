<?php

namespace App\Repositories\Skills;

use App\Models\DevelopmentPlan;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DevelopmentPlanRepository
{
    public function paginate(Request $request, int $perPage): LengthAwarePaginator
    {
        return DevelopmentPlan::with(['employee', 'skill', 'assessment'])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->input('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate($perPage);
    }

    public function forEmployee(Employee $employee): Collection
    {
        return DevelopmentPlan::where('employee_id', $employee->id)
            ->with(['skill', 'assessment'])
            ->latest()
            ->get();
    }

    public function create(array $data): DevelopmentPlan
    {
        return DevelopmentPlan::create($data);
    }

    public function update(DevelopmentPlan $plan, array $data): DevelopmentPlan
    {
        $plan->update($data);

        return $plan->fresh();
    }

    public function delete(DevelopmentPlan $plan): void
    {
        $plan->delete();
    }
}
