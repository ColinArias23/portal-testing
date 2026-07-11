<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReleaseRequest;
use App\Http\Resources\ReleaseResource;
use App\Http\Resources\UnassignedReportResource;
use App\Models\Release;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReleaseController extends Controller
{
    private const REPORT_SUMMARY_COLUMNS = ['id', 'subject', 'type', 'category', 'severity'];

    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'destroy']);
        $this->middleware('role:SuperAdmin')->only(['store', 'destroy']);
        $this->middleware('auth:sanctum')->only(['unassignedResolved']);
        $this->middleware('role:SuperAdmin')->only(['unassignedResolved']);
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100)); 

        $releases = Release::query()
            ->with(['reports:' . implode(',', self::REPORT_SUMMARY_COLUMNS)])
            ->where('status', 'published')
            ->orderBy('date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => ReleaseResource::collection($releases->items()),
            'meta' => [
                'current_page' => $releases->currentPage(),
                'last_page' => $releases->lastPage(),
                'per_page' => $releases->perPage(),
                'total' => $releases->total(),
            ],
        ]);
    }
// ─── Show ─────────────────────────────────────────────────────
    public function show(Release $release): JsonResponse
    {
        if (! $release->isPublished()) {
            abort(404);
        }

        $release->load(['reports:' . implode(',', self::REPORT_SUMMARY_COLUMNS)]);

        return response()->json(['data' => new ReleaseResource($release)]);
    }

    public function store(StoreReleaseRequest $request): JsonResponse
    {
        try {
            $release = DB::transaction(function () use ($request) {
                $release = Release::create([
                    'version' => $request->validated('version'),
                    'title' => $request->validated('title') ?? "Version {$request->validated('version')}",
                    'date' => $request->validated('date') ?? today()->toDateString(),
                    'status' => 'published',
                ]);

                $release->reports()->attach($request->validated('report_ids'));

                return $release;
            });
        } catch (Throwable $e) {
            Log::error('Failed to create release', [
                'error' => $e->getMessage(),
                'version' => $request->validated('version'),
            ]);

            return response()->json([
                'message' => 'Unable to publish release. Please try again.',
            ], 503);
        }

        $release->load(['reports:' . implode(',', self::REPORT_SUMMARY_COLUMNS)]);

        return response()->json([
            'message' => 'Release published successfully.',
            'data' => new ReleaseResource($release),
        ], 201);
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Release $release): JsonResponse
    {
        DB::transaction(function () use ($release) {
            $release->reports()->detach();
            $release->delete();
        });

        return response()->json(['message' => 'Release deleted successfully.']);
    }
// ─── UnaasignedResolved ─────────────────────────────────────────────────────
    public function unassignedResolved(): JsonResponse
    {
        $reports = Report::query()
            ->with('user:id,username')
            ->where('status', 'resolved')
            ->whereDoesntHave('releases')
            ->orderBy('resolved_at', 'desc')
            ->get();

        return response()->json([
            'data' => UnassignedReportResource::collection($reports),
        ]);
    }
}