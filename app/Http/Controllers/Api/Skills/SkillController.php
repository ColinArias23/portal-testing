<?php

namespace App\Http\Controllers\Api\Skills;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Services\Skills\SkillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function __construct(
        private readonly SkillService $skills,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:trainings.view')->only(['index', 'all', 'show']);
        $this->middleware('permission:trainings.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->skills->index($request));
    }

    // ─── All ─────────────────────────────────────────────────────
    public function all(): JsonResponse
    {
        return response()->json(['data' => $this->skills->all()]);
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'category'      => ['required', 'in:clinical,administrative,technical,soft,compliance'],
            'weight'        => ['required', 'in:1,2,3'],
            'display_order' => ['nullable', 'integer'],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Skill created successfully.',
            'data'    => $this->skills->store($validated),
        ], 201);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Skill $skill): JsonResponse
    {
        return response()->json(['data' => $this->skills->show($skill)]);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Skill $skill): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['sometimes', 'required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'category'      => ['sometimes', 'required', 'in:clinical,administrative,technical,soft,compliance'],
            'weight'        => ['sometimes', 'required', 'in:1,2,3'],
            'display_order' => ['nullable', 'integer'],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Skill updated successfully.',
            'data'    => $this->skills->update($skill, $validated),
        ]);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Skill $skill): JsonResponse
    {
        $this->skills->destroy($skill);

        return response()->json(['message' => 'Skill deleted successfully.']);
    }
}
