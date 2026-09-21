<?php

namespace App\Events\Recruitment\PlantillaPosting;

use App\Models\PlantillaPostingApplicationInterview;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlantillaApplicationInterviewUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PlantillaPostingApplicationInterview $interview) {}

    public function broadcastOn(): Channel
    {
        return new Channel('plantilla-application-interviews');
    }

    public function broadcastAs(): string
    {
        return 'plantilla_application_interview.updated';
    }

    public function broadcastWith(): array
    {
        return ['interview' => $this->interview];
    }
}