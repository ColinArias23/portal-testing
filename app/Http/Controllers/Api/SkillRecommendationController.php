<?php
 
namespace App\Http\Controllers\Api;
 
use App\Http\Controllers\Controller;
use App\Models\SkillRecommendation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
 
class SkillRecommendationController extends Controller
{
    /**
     * GET /api/skill-recommendations
     */
    public function index(Request $request): JsonResponse
    {
        $query = SkillRecommendation::with('skill');
 
        if ($request->filled('skill_id')) {
            $query->where('skill_id', $request->input('skill_id'));
        }
 
        return response()->json($query->paginate(50));
    }
 
    /**
     * POST /api/skill-recommendations
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_id'       => ['required', 'exists:skills,id', 'unique:skill_recommendations'],
            'actions'        => ['nullable', 'array'],
            'resources'      => ['nullable', 'array'],
            'manager_support' => ['nullable', 'array'],
            'timeline'       => ['nullable', 'string'],
        ]);
 
        $recommendation = SkillRecommendation::create($validated);
 
        return response()->json([
            'message' => 'Recommendation created.',
            'data'    => $recommendation,
        ], 201);
    }
 
    /**
     * GET /api/skill-recommendations/{id}
     */
    public function show(SkillRecommendation $recommendation): JsonResponse
    {
        return response()->json([
            'data' => $recommendation->load('skill'),
        ]);
    }
 
    /**
     * PUT /api/skill-recommendations/{id}
     */
    public function update(Request $request, SkillRecommendation $recommendation): JsonResponse
    {
        $validated = $request->validate([
            'actions'        => ['nullable', 'array'],
            'resources'      => ['nullable', 'array'],
            'manager_support' => ['nullable', 'array'],
            'timeline'       => ['nullable', 'string'],
        ]);
 
        $recommendation->update($validated);
 
        return response()->json([
            'message' => 'Recommendation updated.',
            'data'    => $recommendation->fresh(),
        ]);
    }
 
    /**
     * DELETE /api/skill-recommendations/{id}
     */
    public function destroy(SkillRecommendation $recommendation): JsonResponse
    {
        $recommendation->delete();
 
        return response()->json([
            'message' => 'Recommendation deleted.',
        ]);
    }
 
    /**
     * GET /api/skill-recommendations/skill/{skillId}
     */
    public function bySkill($skillId): JsonResponse
    {
        $recommendation = SkillRecommendation::where('skill_id', $skillId)->first();
 
        return response()->json([
            'data' => $recommendation,
        ]);
    }
}
