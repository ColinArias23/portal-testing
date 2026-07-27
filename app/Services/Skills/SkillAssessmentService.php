<?php

namespace App\Services\Skills;

use App\Models\Employee;
use App\Models\SkillAssessment;
use App\Repositories\Skills\SkillAssessmentRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SkillAssessmentService
{
    public function __construct(
        private readonly SkillAssessmentRepository $assessments,
    ) {
    }

    public function index(Request $request): LengthAwarePaginator
    {
        return $this->assessments->paginate($request);
    }

    public function store(array $validated): array
    {
        $employee = Employee::findOrFail($validated['employee_id']);
        $departmentId = collect($employee->department_ids ?? [])->first();

        if (! $departmentId) {
            return ['status' => 422, 'body' => ['message' => 'This employee does not belong to any department.']];
        }

        $assessment = $this->assessments->findPendingForEmployee($employee->id);

        if ($assessment) {
            $this->assessments->update($assessment, ['scores' => $validated['scores']]);
        } else {
            if ($this->assessments->doneExistsForEmployee($employee->id)) {
                return ['status' => 422, 'body' => ['message' => 'This employee has already completed their assessment.']];
            }

            $assessment = $this->assessments->create([
                'employee_id'   => $employee->id,
                'department_id' => $departmentId,
                'scores'        => $validated['scores'],
                'status'        => SkillAssessment::STATUS_PENDING,
            ]);
        }

        $assessment->calculateMetrics()->submit();

        return [
            'status' => 201,
            'body' => [
                'message'      => 'Assessment submitted successfully.',
                'status'       => $assessment->status,
                'submitted_at' => $assessment->submitted_at,
                'data'         => $assessment->fresh()->load(['employee', 'department']),
            ],
        ];
    }

    public function show(SkillAssessment $assessment): SkillAssessment
    {
        return $assessment->load(['employee', 'department']);
    }

    public function update(SkillAssessment $assessment, array $validated): array
    {
        if ($assessment->isDone()) {
            return [
                'status' => 422,
                'body' => [
                    'message'      => 'This assessment has already been submitted and cannot be modified.',
                    'status'       => $assessment->status,
                    'submitted_at' => $assessment->submitted_at,
                ],
            ];
        }

        $this->assessments->update($assessment, $validated);

        return [
            'status' => 200,
            'body' => ['message' => 'Assessment updated.', 'data' => $assessment->fresh()],
        ];
    }

    public function submit(SkillAssessment $assessment): array
    {
        if ($assessment->isDone()) {
            return [
                'status' => 422,
                'body' => [
                    'message'      => 'This assessment has already been submitted.',
                    'status'       => $assessment->status,
                    'submitted_at' => $assessment->submitted_at,
                ],
            ];
        }

        if (empty($assessment->scores)) {
            return [
                'status' => 422,
                'body' => ['message' => 'Cannot submit an assessment with no scores.', 'status' => $assessment->status],
            ];
        }

        $assessment->calculateMetrics()->submit();

        return [
            'status' => 200,
            'body' => [
                'message'      => 'Assessment submitted successfully.',
                'status'       => $assessment->status,
                'submitted_at' => $assessment->submitted_at,
                'data'         => $assessment->fresh()->load(['employee', 'department']),
            ],
        ];
    }

    public function destroy(SkillAssessment $assessment): array
    {
        if ($assessment->isDone()) {
            return [
                'status' => 422,
                'body' => ['message' => 'A submitted assessment cannot be deleted.', 'status' => $assessment->status],
            ];
        }

        $this->assessments->delete($assessment);

        return ['status' => 200, 'body' => ['message' => 'Assessment deleted.']];
    }
}
