<?php

namespace App\Events\Recruitment\Hiring;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class OnboardingUpdated implements ShouldBroadcastNow
{
    use SerializesModels;

    public array $onboarding;

    public function __construct(array $onboarding)
    {
        $this->onboarding = $onboarding;
    }

    public function broadcastOn(): Channel
    {
        return empty($this->onboarding['type'])
            ? new Channel('onboardings')
            : new PrivateChannel('plantilla-onboardings');
    }

    public function broadcastAs(): string
    {
        return 'onboarding.updated';
    }

    public function broadcastWith(): array
    {
        return ['onboarding' => $this->onboarding];
    }
}