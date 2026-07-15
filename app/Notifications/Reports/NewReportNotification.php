<?php

namespace App\Notifications\Reports;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Report $report) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'subject'   => $this->report->subject,
            'type'      => $this->report->type,
            'severity'  => $this->report->severity,
            'category'  => $this->report->category,
            'status'    => $this->report->status,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $severityLabel = $this->report->severity
            ? ucfirst($this->report->severity)
            : 'N/A';

        $typeLabel = ucfirst($this->report->type);

        return (new MailMessage)
            ->subject("[{$typeLabel}] New report: {$this->report->subject}")
            ->greeting('Hello ' . ($notifiable->username ?? 'there') . ',')
            ->line('A new report has been submitted and requires your attention.')
            ->line("Subject: {$this->report->subject}")
            ->line("Type: {$typeLabel}")
            ->line("Category: {$this->report->category}")
            ->line("Severity: {$severityLabel}")
            ->action('View Report', url("/admin/reports/{$this->report->id}"))
            ->line('Thank you for keeping the platform healthy.');
    }
}