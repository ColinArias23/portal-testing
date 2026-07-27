<?php

namespace App\Services\Skills;

use App\Models\SkillRecommendation;
use App\Repositories\Skills\SkillRecommendationRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SkillRecommendationService
{
    public function __construct(
        private readonly SkillRecommendationRepository $recommendations,
    ) {
    }

    public function index(Request $request): LengthAwarePaginator
    {
        return $this->recommendations->paginate($request);
    }

    public function store(array $data): SkillRecommendation
    {
        return $this->recommendations->create($data);
    }

    public function show(SkillRecommendation $recommendation): SkillRecommendation
    {
        return $recommendation->load('skill');
    }

    public function update(SkillRecommendation $recommendation, array $data): SkillRecommendation
    {
        return $this->recommendations->update($recommendation, $data);
    }

    public function destroy(SkillRecommendation $recommendation): void
    {
        $this->recommendations->delete($recommendation);
    }

    public function bySkill(int $skillId): ?SkillRecommendation
    {
        return $this->recommendations->findForSkill($skillId);
    }
}
