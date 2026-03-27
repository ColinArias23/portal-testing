<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
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
        return [new Channel('trainings')];
    }

    /**
     * Frontend listens with: channel.listen('.training.deleted', handler)
     */
    public function broadcastAs(): string
    {
        return 'training.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->trainingId];
    }
}