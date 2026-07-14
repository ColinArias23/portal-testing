<?php

namespace App\Events\Recruitment\Hiring;

use App\Models\Hiring\Onboarding;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class OnboardingUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public array $onboarding;

    public function __construct(array $onboarding)
    {
        $this->onboarding = $onboarding;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('onboardings');
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