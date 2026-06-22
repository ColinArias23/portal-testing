<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreReportRequest;
use App\Jobs\ProcessNewReport;
use App\Models\Report;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
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
}