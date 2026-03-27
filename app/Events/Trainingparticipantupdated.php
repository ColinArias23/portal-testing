<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainingParticipantUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $training;

    public function __construct(array $training)
    {
        $this->training = $training;
    }

    /**
     * Returns an array to stay consistent with Laravel's broadcastOn() signature.
     * Your original returned a single Channel — both work, but array is cleaner.
     */
    public function broadcastOn(): array
    {
        return [new Channel('trainings')];
    }

    public function broadcastAs(): string
    {
        return 'participant.updated';
    }

    public function broadcastWith(): array
    {
        return ['training' => $this->training];
    }
}