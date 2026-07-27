<?php

namespace App\Services\Reports;

use App\Jobs\Reports\ProcessNewReport;
use App\Models\Report;
use App\Repositories\Reports\ReportRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportService
{
    public function __construct(
        private readonly ReportRepository $reports,
    ) {
    }

    public function index(Request $request): LengthAwarePaginator
    {
        return $this->reports->paginate($request, min((int) $request->get('per_page', 25), 100));
    }

    public function store(array $validated, int $userId): Report
    {
        $report = $this->reports->create([
            ...$validated,
            'user_id' => $userId,
            'status'  => 'open',
        ]);

        dispatch(new ProcessNewReport($report));

        return $report;
    }

    public function update(Report $report, array $validated): Report
    {
        return $this->reports->update($report, $validated);
    }

    public function destroy(Report $report): void
    {
        $this->reports->delete($report);
    }
}
