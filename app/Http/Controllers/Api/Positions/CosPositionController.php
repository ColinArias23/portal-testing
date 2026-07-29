<?php

namespace App\Http\Controllers\Api\Positions;

use App\Http\Controllers\Controller;
use App\Models\CosPosition;
use App\Services\Positions\CosPositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CosPositionController extends Controller
{
    public function __construct(
        private readonly CosPositionService $positions,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:positions.view')->only(['index', 'show']);
        $this->middleware('permission:employees.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->positions->index($request));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'COS position created successfully.',
            'data'    => $this->positions->store($validated),
        ], 201);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(CosPosition $cosPosition): JsonResponse
    {
        return response()->json(['data' => $cosPosition]);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, CosPosition $cosPosition): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'COS position updated successfully.',
            'data'    => $this->positions->update($cosPosition, $validated),
        ]);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(CosPosition $cosPosition): JsonResponse
    {
        $this->positions->destroy($cosPosition);

        return response()->json(['message' => 'COS position deleted successfully.']);
    }
}