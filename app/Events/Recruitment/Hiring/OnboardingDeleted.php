<?php

namespace App\Events\Recruitment\Hiring;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class OnboardingDeleted implements ShouldBroadcastNow
{
    public int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('onboardings');
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