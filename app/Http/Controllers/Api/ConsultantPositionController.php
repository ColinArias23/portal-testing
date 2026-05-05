<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultantPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultantPositionController extends Controller
{
    /**
     * GET /api/consultant-positions
     * Query params: search, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = ConsultantPosition::query()->orderBy('title');

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $perPage = (int) $request->input('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    /**
     * POST /api/consultant-positions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $position = ConsultantPosition::create($validated);

        return response()->json([
            'message' => 'Consultant position created successfully.',
            'data'    => $position,
        ], 201);
    }

    /**
     * GET /api/consultant-positions/{id}
     */
    public function show(ConsultantPosition $consultantPosition): JsonResponse
    {
        return response()->json(['data' => $consultantPosition]);
    }

    /**
     * PUT /api/consultant-positions/{id}
     */
    public function update(Request $request, ConsultantPosition $consultantPosition): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $consultantPosition->update($validated);

        return response()->json([
            'message' => 'Consultant position updated successfully.',
            'data'    => $consultantPosition->fresh(),
        ]);
    }

    /**
     * DELETE /api/consultant-positions/{id}
     */
    public function destroy(ConsultantPosition $consultantPosition): JsonResponse
    {
        $consultantPosition->delete();

        return response()->json([
            'message' => 'Consultant position deleted successfully.',
        ]);
    }
}