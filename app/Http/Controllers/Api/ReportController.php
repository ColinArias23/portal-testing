<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Jobs\ProcessNewReport;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * List all reports — admin only.
     * Supports filtering by ?type=, ?status=, ?category=, ?severity=
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        $allowed = ['type', 'status', 'category', 'severity'];

        $reports = Report::with('user:id,username')
            ->when($request->filled('type'),     fn ($q) => $q->where('type',     $request->type))
            ->when($request->filled('status'),   fn ($q) => $q->where('status',   $request->status))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->severity))
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return response()->json($reports);
    }

    public function show(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $report->load('user:id,username');

        return response()->json(['data' => $report]);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = Report::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status'  => 'open',
        ]);

        dispatch(new ProcessNewReport($report));

        return response()->json([
            'message' => 'Report submitted successfully.',
            'data'    => [
                'id'         => $report->id,
                'subject'    => $report->subject,
                'type'       => $report->type,
                'category'   => $report->category,
                'severity'   => $report->severity,
                'status'     => $report->status,
                'created_at' => $report->created_at->toISOString(),
            ],
        ], 201);
    }

    public function update(UpdateReportRequest $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $report->update($request->validated());

        return response()->json([
            'message' => 'Report updated successfully.',
            'data'    => $report->fresh(),
        ]);
    }

    public function destroy(Report $report): JsonResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return response()->json(['message' => 'Report deleted successfully.']);
    }
}