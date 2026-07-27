<?php

namespace App\Events\Training;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainingDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $trainingId;

    public function __construct(int $trainingId)
    {
        $this->trainingId = $trainingId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('trainings')];
    }

    public function broadcastAs(): string
    {
        return 'training.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->trainingId];
    }
}