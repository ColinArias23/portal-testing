<?php

namespace App\Repositories\Recruitment\PlantillaPosting;

use App\Models\PlantillaPostingApplicationInterview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PlantillaApplicationInterviewRepository
{
    private const EAGER_LOAD = ['plantillaPostingApplication.employee', 'plantillaPostingApplication.posting'];

    public function newQuery(): Builder
    {
        return PlantillaPostingApplicationInterview::query()->with(self::EAGER_LOAD);
    }

    public function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function findForApplication(int $applicationId): PlantillaPostingApplicationInterview
    {
        return PlantillaPostingApplicationInterview::where('plantilla_posting_application_id', $applicationId)
            ->with(self::EAGER_LOAD)
            ->firstOrFail();
    }

    public function lockExistingForApplication(int $applicationId): ?PlantillaPostingApplicationInterview
    {
        return PlantillaPostingApplicationInterview::where('plantilla_posting_application_id', $applicationId)
            ->lockForUpdate()
            ->first();
    }

    public function create(array $attributes): PlantillaPostingApplicationInterview
    {
        return PlantillaPostingApplicationInterview::create($attributes);
    }

    public function loadRelations(PlantillaPostingApplicationInterview $interview): PlantillaPostingApplicationInterview
    {
        return $interview->load(self::EAGER_LOAD);
    }
}
