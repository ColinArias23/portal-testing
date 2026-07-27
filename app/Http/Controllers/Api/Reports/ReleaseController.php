<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReleaseRequest;
use App\Http\Resources\Reports\ReleaseResource;
use App\Http\Resources\Reports\UnassignedReportResource;
use App\Models\Release;
use App\Services\Reports\ReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseController extends Controller
{
    public function __construct(
        private readonly ReleaseService $releases,
    ) {
        $this->middleware('auth:sanctum')->only(['store', 'destroy']);
        $this->middleware('role:SuperAdmin')->only(['store', 'destroy']);
        $this->middleware('auth:sanctum')->only(['unassignedResolved']);
        $this->middleware('role:SuperAdmin')->only(['unassignedResolved']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $releases = $this->releases->index($perPage);

        return response()->json([
            'data' => ReleaseResource::collection($releases->items()),
            'meta' => [
                'current_page' => $releases->currentPage(),
                'last_page'    => $releases->lastPage(),
                'per_page'     => $releases->perPage(),
                'total'        => $releases->total(),
            ],
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Release $release): JsonResponse
    {
        return response()->json(['data' => new ReleaseResource($this->releases->show($release))]);
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(StoreReleaseRequest $request): JsonResponse
    {
        $result = $this->releases->store($request->validated());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Release published successfully.',
            'data'    => new ReleaseResource($result['release']),
        ], 201);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Release $release): JsonResponse
    {
        $this->releases->destroy($release);

        return response()->json(['message' => 'Release deleted successfully.']);
    }

    // ─── UnassignedResolved ─────────────────────────────────────────────────────
    public function unassignedResolved(): JsonResponse
    {
        return response()->json([
            'data' => UnassignedReportResource::collection($this->releases->unassignedResolved()),
        ]);
    }
}
