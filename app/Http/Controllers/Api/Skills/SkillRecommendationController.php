<?php

namespace App\Http\Controllers\Api\Skills;

use App\Http\Controllers\Controller;
use App\Models\SkillRecommendation;
use App\Services\Skills\SkillRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillRecommendationController extends Controller
{
    public function __construct(
        private readonly SkillRecommendationService $recommendations,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:trainings.view')->only(['index', 'show', 'bySkill']);
        $this->middleware('permission:trainings.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->recommendations->index($request));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_id'        => ['required', 'exists:skills,id', 'unique:skill_recommendations'],
            'actions'         => ['nullable', 'array'],
            'resources'       => ['nullable', 'array'],
            'manager_support' => ['nullable', 'array'],
            'timeline'        => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Recommendation created.',
            'data'    => $this->recommendations->store($validated),
        ], 201);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(SkillRecommendation $recommendation): JsonResponse
    {
        return response()->json(['data' => $this->recommendations->show($recommendation)]);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, SkillRecommendation $recommendation): JsonResponse
    {
        $validated = $request->validate([
            'actions'         => ['nullable', 'array'],
            'resources'       => ['nullable', 'array'],
            'manager_support' => ['nullable', 'array'],
            'timeline'        => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Recommendation updated.',
            'data'    => $this->recommendations->update($recommendation, $validated),
        ]);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(SkillRecommendation $recommendation): JsonResponse
    {
        $this->recommendations->destroy($recommendation);

        return response()->json(['message' => 'Recommendation deleted.']);
    }

    // ─── BySkill ─────────────────────────────────────────────────────
    public function bySkill($skillId): JsonResponse
    {
        return response()->json(['data' => $this->recommendations->bySkill((int) $skillId)]);
    }
}
