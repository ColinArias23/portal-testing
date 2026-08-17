<?php

namespace App\Jobs\Reports;

use App\Events\Reports\ReportSubmitted;
use App\Support\Broadcasting\SafeBroadcast;
use App\Models\Report;
use App\Models\User;
use App\Notifications\Reports\NewReportNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessNewReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly Report $report) {}

    public function handle(): void
    {
        $report = $this->report->loadMissing('user');

        $this->broadcastToAdmins($report);
        $this->notifyAdmins($report);
    }

    private function broadcastToAdmins(Report $report): void
    {
        try {
            SafeBroadcast::send(new ReportSubmitted($report));
        } catch (\Throwable $e) {
            Log::error('ReportSubmitted broadcast failed', [
                'report_id' => $report->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function notifyAdmins(Report $report): void
    {
        $admins = User::role('SuperAdmin')->get();

        if ($admins->isEmpty()) {
            Log::warning('ProcessNewReport: No admin users found to notify.', [
                'report_id' => $report->id,
            ]);
            return;
        }

        Notification::send($admins, new NewReportNotification($report));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessNewReport job failed permanently', [
            'report_id' => $this->report->id,
            'error'     => $exception->getMessage(),
        ]);
    }
}