<?php

namespace App\Events\Training;

use App\Models\Training;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainingCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Training $training;
    public int      $postedByUserId;
    public array    $formatted;

    public function __construct(Training $training, int $postedByUserId, array $formatted)
    {
        $this->training       = $training;
        $this->postedByUserId = $postedByUserId;
        $this->formatted      = $formatted;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications'),
            new PrivateChannel('trainings'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        $t = $this->training;

        return [
            'type'              => 'training',
            'title'             => 'New Training Program',
            'message'           => 'A new training program has been posted.',
            'posted_by_user_id' => $this->postedByUserId,
            'data'              => [
                'program'    => $t->title,
                'department' => $t->department,
                'category'   => $t->category,
                'location'   => $t->event_address,
                'mode'       => $t->training_mode,
                'start_date' => $t->start_date?->toISOString(),
                'end_date'   => $t->end_date?->toISOString(),
                'duration'   => $t->duration,
            ],

            'training'          => $this->formatted,
        ];
    }
}