<?php

namespace App\Repositories\Skills;

use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SkillRepository
{
    public function paginate(Request $request, int $perPage): LengthAwarePaginator
    {
        return Skill::query()
            ->when($request->filled('category'), fn ($q) => $q->byCategory($request->input('category')))
            ->when($request->has('active'), fn ($q) => $q->where(
                'is_active',
                filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN)
            ))
            ->orderByDisplay()
            ->paginate($perPage);
    }

    public function allActive(): Collection
    {
        return Skill::active()->with('recommendation')->orderByDisplay()->get();
    }

    public function create(array $data): Skill
    {
        return Skill::create($data);
    }

    public function update(Skill $skill, array $data): Skill
    {
        $skill->update($data);

        return $skill->fresh();
    }

    public function delete(Skill $skill): void
    {
        $skill->delete();
    }
}
