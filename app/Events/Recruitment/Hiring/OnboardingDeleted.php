<?php

namespace App\Events\Recruitment\Hiring;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class OnboardingDeleted implements ShouldBroadcastNow
{
    public int $id;

    public bool $plantilla;

    public function __construct(int $id, bool $plantilla = false)
    {
        $this->id = $id;
        $this->plantilla = $plantilla;
    }

    public function broadcastOn(): Channel
    {
        return $this->plantilla
            ? new PrivateChannel('plantilla-onboardings')
            : new Channel('onboardings');
    }

    public function broadcastAs(): string
    {
        return 'onboarding.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->id];
    }
}