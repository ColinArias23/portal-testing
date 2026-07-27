<?php

namespace App\Services\Skills;

use App\Models\Skill;
use App\Repositories\Skills\SkillRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SkillService
{
    public function __construct(
        private readonly SkillRepository $skills,
    ) {
    }

    public function index(Request $request): LengthAwarePaginator
    {
        return $this->skills->paginate($request, (int) $request->input('per_page', 50));
    }

    public function all(): Collection
    {
        return $this->skills->allActive();
    }

    public function store(array $data): Skill
    {
        return $this->skills->create($data);
    }

    public function show(Skill $skill): Skill
    {
        return $skill->load('recommendation');
    }

    public function update(Skill $skill, array $data): Skill
    {
        return $this->skills->update($skill, $data);
    }

    public function destroy(Skill $skill): void
    {
        $this->skills->delete($skill);
    }
}
