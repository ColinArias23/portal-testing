<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CosPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CosPositionController extends Controller
{
    /**
     * GET /api/cos-positions
     * Query params: search, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = CosPosition::query()->orderBy('title');

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $perPage = (int) $request->input('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    /**
     * POST /api/cos-positions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $position = CosPosition::create($validated);

        return response()->json([
            'message' => 'COS position created successfully.',
            'data'    => $position,
        ], 201);
    }

    /**
     * GET /api/cos-positions/{id}
     */
    public function show(CosPosition $cosPosition): JsonResponse
    {
        return response()->json(['data' => $cosPosition]);
    }

    /**
     * PUT /api/cos-positions/{id}
     */
    public function update(Request $request, CosPosition $cosPosition): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $cosPosition->update($validated);

        return response()->json([
            'message' => 'COS position updated successfully.',
            'data'    => $cosPosition->fresh(),
        ]);
    }

    /**
     * DELETE /api/cos-positions/{id}
     */
    public function destroy(CosPosition $cosPosition): JsonResponse
    {
        $cosPosition->delete();

        return response()->json([
            'message' => 'COS position deleted successfully.',
        ]);
    }
}