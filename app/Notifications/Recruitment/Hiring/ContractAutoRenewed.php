<?php

namespace App\Notifications\Recruitment\Hiring;

use App\Models\Hiring\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractAutoRenewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Contract $contract) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $start = $this->contract->start_date?->format('M d, Y') ?? '—';
        $end   = $this->contract->end_date?->format('M d, Y')   ?? '—';

        return (new MailMessage)
            ->subject("Contract Auto-Renewed · {$this->contract->employee_name}")
            ->greeting("Hello, {$notifiable->first_name}!")
            ->line('Your employment contract has been automatically renewed.')
            ->line("**New Period:** {$start} → {$end}")
            ->line("**Duration:** {$this->contract->auto_renewal_duration_months} months")
            ->line('Please contact HR if you have any questions about this renewal.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'contract_auto_renewed',
            'title'           => 'Contract Auto-Renewed',
            'message'         => "Your contract has been automatically renewed. New period: {$this->contract->start_date?->format('M d, Y')} – {$this->contract->end_date?->format('M d, Y')}.",
            'contract_id'     => $this->contract->id,
            'employee_name'   => $this->contract->employee_name,
            'start_date'      => $this->contract->start_date?->toDateString(),
            'end_date'        => $this->contract->end_date?->toDateString(),
            'renewal_gen'     => $this->contract->renewal_generation,
        ];
    }
}