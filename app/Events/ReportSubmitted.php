<?php

namespace App\Events;

use App\Models\Report;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Report $report) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.reports')];
    }

    public function broadcastAs(): string
    {
        return 'report.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->report->id,
            'subject'      => $this->report->subject,
            'type'         => $this->report->type,
            'category'     => $this->report->category,
            'severity'     => $this->report->severity,
            'status'       => $this->report->status,
            'submitted_by' => $this->report->user?->name ?? 'Unknown',
            'created_at'   => $this->report->created_at->toISOString(),
        ];
    }
}