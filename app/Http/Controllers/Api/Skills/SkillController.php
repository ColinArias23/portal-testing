<?php

namespace App\Http\Controllers\Api\Skills;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:trainings.view')->only(['index', 'all', 'show']);
        $this->middleware('permission:trainings.manage')->only(['store', 'update', 'destroy']);
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Skill::query();

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        if ($request->has('active')) {
            $query->where('is_active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->input('per_page', 50);

        return response()->json($query->orderByDisplay()->paginate($perPage));
    }
// ─── All ─────────────────────────────────────────────────────
    public function all(): JsonResponse
    {
        $skills = Skill::active()
            ->with('recommendation')
            ->orderByDisplay()
            ->get();

        return response()->json([
            'data' => $skills,
        ]);
    }
// ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'category'       => ['required', 'in:clinical,administrative,technical,soft,compliance'],
            'weight'         => ['required', 'in:1,2,3'],
            'display_order'  => ['nullable', 'integer'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $skill = Skill::create($validated);

        return response()->json([
            'message' => 'Skill created successfully.',
            'data'    => $skill,
        ], 201);
    }
// ─── Show ─────────────────────────────────────────────────────
    public function show(Skill $skill): JsonResponse
    {
        return response()->json([
            'data' => $skill->load('recommendation'),
        ]);
    }
// ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Skill $skill): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['sometimes', 'required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'category'       => ['sometimes', 'required', 'in:clinical,administrative,technical,soft,compliance'],
            'weight'         => ['sometimes', 'required', 'in:1,2,3'],
            'display_order'  => ['nullable', 'integer'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $skill->update($validated);

        return response()->json([
            'message' => 'Skill updated successfully.',
            'data'    => $skill->fresh(),
        ]);
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Skill $skill): JsonResponse
    {
        $skill->delete();

        return response()->json([
            'message' => 'Skill deleted successfully.',
        ]);
    }
}