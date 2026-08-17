<?php

namespace App\Events\Recruitment\Hiring;

use App\Models\Hiring\Interview;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InterviewUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Interview $interview) {}

    public function broadcastOn(): Channel
    {
        return new Channel('interviews');
    }

    public function broadcastAs(): string
    {
        return 'interview.updated';
    }

    public function broadcastWith(): array
    {
        return ['interview' => $this->interview];
    }
}