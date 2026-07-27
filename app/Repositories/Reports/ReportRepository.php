<?php

namespace App\Repositories\Reports;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportRepository
{
    public function paginate(Request $request, int $perPage): LengthAwarePaginator
    {
        return Report::with('user:id,username')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->severity))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): Report
    {
        return Report::create($data)->load('user:id,username');
    }

    public function update(Report $report, array $data): Report
    {
        $report->update($data);

        return $report->fresh('user');
    }

    public function delete(Report $report): void
    {
        $report->delete();
    }
}
