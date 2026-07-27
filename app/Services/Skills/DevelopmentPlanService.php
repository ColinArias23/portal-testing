<?php

namespace App\Services\Skills;

use App\Models\DevelopmentPlan;
use App\Models\Employee;
use App\Repositories\Skills\DevelopmentPlanRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DevelopmentPlanService
{
    public function __construct(
        private readonly DevelopmentPlanRepository $plans,
    ) {
    }

    public function index(Request $request): LengthAwarePaginator
    {
        return $this->plans->paginate($request, (int) $request->input('per_page', 15));
    }

    public function store(array $data): DevelopmentPlan
    {
        return $this->plans->create($data);
    }

    public function show(DevelopmentPlan $plan): DevelopmentPlan
    {
        return $plan->load(['employee', 'skill', 'assessment']);
    }

    public function byEmployee(Employee $employee): Collection
    {
        return $this->plans->forEmployee($employee);
    }

    public function update(DevelopmentPlan $plan, array $data): DevelopmentPlan
    {
        return $this->plans->update($plan, $data);
    }

    public function updateProgress(DevelopmentPlan $plan, int $progress, ?string $notes): DevelopmentPlan
    {
        $plan->updateProgress($progress);

        if ($notes !== null) {
            $plan->notes = $notes;
            $plan->save();
        }

        return $plan->fresh();
    }

    public function complete(DevelopmentPlan $plan): DevelopmentPlan
    {
        $plan->markAsCompleted();

        return $plan->fresh();
    }

    public function destroy(DevelopmentPlan $plan): void
    {
        $this->plans->delete($plan);
    }
}
