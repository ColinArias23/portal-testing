<?php

namespace App\Http\Controllers\Api\Positions;

use App\Http\Controllers\Controller;
use App\Models\ConsultantPosition;
use App\Services\Positions\ConsultantPositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultantPositionController extends Controller
{
    public function __construct(
        private readonly ConsultantPositionService $positions,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:employees.view')->only(['index', 'show']);
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
            'message' => 'Consultant position created successfully.',
            'data'    => $this->positions->store($validated),
        ], 201);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(ConsultantPosition $consultantPosition): JsonResponse
    {
        return response()->json(['data' => $consultantPosition]);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, ConsultantPosition $consultantPosition): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Consultant position updated successfully.',
            'data'    => $this->positions->update($consultantPosition, $validated),
        ]);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(ConsultantPosition $consultantPosition): JsonResponse
    {
        $this->positions->destroy($consultantPosition);

        return response()->json(['message' => 'Consultant position deleted successfully.']);
    }
}
