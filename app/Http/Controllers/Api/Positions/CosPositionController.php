<?php

namespace App\Http\Controllers\Api\Positions;

use App\Http\Controllers\Controller;
use App\Models\CosPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CosPositionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:employees.view')->only(['index', 'show']);
        $this->middleware('permission:employees.manage')->only(['store', 'update', 'destroy']);
    }
    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = CosPosition::query()->orderBy('title');

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $perPage = (int) $request->input('per_page', 15);

        return response()->json($query->paginate($perPage));
    }
// ─── Store ─────────────────────────────────────────────────────
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

        $cosPosition->update($validated);

        return response()->json([
            'message' => 'COS position updated successfully.',
            'data'    => $cosPosition->fresh(),
        ]);
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(CosPosition $cosPosition): JsonResponse
    {
        $cosPosition->delete();

        return response()->json([
            'message' => 'COS position deleted successfully.',
        ]);
    }
}