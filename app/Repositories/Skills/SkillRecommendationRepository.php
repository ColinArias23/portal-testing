<?php

namespace App\Repositories\Skills;

use App\Models\SkillRecommendation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SkillRecommendationRepository
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        return SkillRecommendation::with('skill')
            ->when($request->filled('skill_id'), fn ($q) => $q->where('skill_id', $request->input('skill_id')))
            ->paginate(50);
    }

    public function create(array $data): SkillRecommendation
    {
        return SkillRecommendation::create($data);
    }

    public function update(SkillRecommendation $recommendation, array $data): SkillRecommendation
    {
        $recommendation->update($data);

        return $recommendation->fresh();
    }

    public function delete(SkillRecommendation $recommendation): void
    {
        $recommendation->delete();
    }

    public function findForSkill(int $skillId): ?SkillRecommendation
    {
        return SkillRecommendation::where('skill_id', $skillId)->first();
    }
}
