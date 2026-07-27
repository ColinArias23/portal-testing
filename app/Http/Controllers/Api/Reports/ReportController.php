<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Http\Requests\Reports\UpdateReportRequest;
use App\Models\Report;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {
        $this->middleware('auth:sanctum');
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        return response()->json($this->reports->index($request));
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return response()->json($report->load('user:id,username'));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(StoreReportRequest $request): JsonResponse
    {
        return response()->json($this->reports->store($request->validated(), $request->user()->id), 201);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(UpdateReportRequest $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        return response()->json($this->reports->update($report, $request->validated()));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Report $report): JsonResponse
    {
        $this->authorize('delete', $report);

        $this->reports->destroy($report);

        return response()->json(['message' => 'Report deleted.']);
    }
}
