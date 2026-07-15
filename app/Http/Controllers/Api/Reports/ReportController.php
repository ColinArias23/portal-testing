<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Http\Requests\Reports\UpdateReportRequest;
use App\Jobs\Reports\ProcessNewReport;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        $reports = Report::with('user:id,username')
            ->when($request->filled('type'),     fn ($q) => $q->where('type',     $request->type))
            ->when($request->filled('status'),   fn ($q) => $q->where('status',   $request->status))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->severity))
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->get('per_page', 25), 100));

        return response()->json($reports);
    }
// ─── Show ─────────────────────────────────────────────────────
    public function show(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $report->load('user:id,username');

        return response()->json($report);
    }
// ─── Store ─────────────────────────────────────────────────────
    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = Report::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status'  => 'open',
        ]);

        dispatch(new ProcessNewReport($report));

        $report->load('user:id,username');

        return response()->json($report, 201);
    }
// ─── Update ─────────────────────────────────────────────────────
    public function update(UpdateReportRequest $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $report->update($request->validated());

        $report->load('user:id,username');

        return response()->json($report->fresh('user'));
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Report $report): JsonResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return response()->json(['message' => 'Report deleted.']);
    }
}