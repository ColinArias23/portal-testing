<?php

namespace App\Repositories\Skills;

use App\Models\SkillAssessment;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SkillAssessmentRepository
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        return SkillAssessment::with(['employee', 'department'])
            ->when($request->filled('employee_id'), fn ($q) => $q->byEmployee((int) $request->input('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latestSubmitted()
            ->paginate(50);
    }

    public function findPendingForEmployee(int $employeeId): ?SkillAssessment
    {
        return SkillAssessment::where('employee_id', $employeeId)
            ->where('status', SkillAssessment::STATUS_PENDING)
            ->first();
    }

    public function doneExistsForEmployee(int $employeeId): bool
    {
        return SkillAssessment::where('employee_id', $employeeId)
            ->where('status', SkillAssessment::STATUS_DONE)
            ->exists();
    }

    public function create(array $data): SkillAssessment
    {
        return SkillAssessment::create($data);
    }

    public function update(SkillAssessment $assessment, array $data): void
    {
        $assessment->update($data);
    }

    public function delete(SkillAssessment $assessment): void
    {
        $assessment->delete();
    }
}
