<?php

namespace App\Events\Training;

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