<?php

namespace App\Repositories\Recruitment\Hiring;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Interview;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ApplicantRepository
{
    private const RELATIONS = ['documents', 'interview', 'contract', 'onboarding'];

    public function newQuery(): Builder
    {
        return Applicant::with(self::RELATIONS)->orderByDesc('created_at');
    }

    public function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query->paginate($perPage);
    }

    public function create(array $attributes): Applicant
    {
        return Applicant::create($attributes);
    }

    public function createInterview(int $applicantId): void
    {
        Interview::create(['applicant_id' => $applicantId]);
    }

    public function addDocument(Applicant $applicant, string $name): void
    {
        $applicant->documents()->create(['name' => trim($name), 'status' => 'Submitted']);
    }

    public function loadRelations(Applicant $applicant): Applicant
    {
        return $applicant->load(self::RELATIONS);
    }

    public function update(Applicant $applicant, array $data): void
    {
        $applicant->update($data);
    }

    public function delete(Applicant $applicant): void
    {
        $applicant->delete();
    }

    public function findTrashed(int $id): ?Applicant
    {
        return Applicant::onlyTrashed()->find($id);
    }

    public function summaryCounts(): array
    {
        $total = Applicant::count();

        $statusCounts = Applicant::whereIn('status', ['FOR INTERVIEW', 'HIRED', 'REJECTED', 'NO SHOW'])
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'total'         => $total,
            'for_interview' => (int) ($statusCounts['FOR INTERVIEW'] ?? 0),
            'hired'         => (int) ($statusCounts['HIRED'] ?? 0),
            'rejected'      => (int) ($statusCounts['REJECTED'] ?? 0),
            'no_show'       => (int) ($statusCounts['NO SHOW'] ?? 0),
        ];
    }

    public function onboardingExistsFor(int $applicantId): bool
    {
        return Onboarding::where('applicant_id', $applicantId)->exists();
    }

    public function createOnboarding(array $attributes): Onboarding
    {
        return Onboarding::create($attributes);
    }

    public function createOnboardingTasks(int $onboardingId, array $tasks): void
    {
        foreach ($tasks as $index => $title) {
            OnboardingTask::create([
                'onboarding_id' => $onboardingId,
                'title'         => $title,
                'completed'     => false,
                'sort_order'    => $index,
            ]);
        }
    }

    public function contractExistsFor(int $applicantId): bool
    {
        return Contract::where('applicant_id', $applicantId)->exists();
    }

    public function createContract(array $attributes): void
    {
        Contract::create($attributes);
    }
}
