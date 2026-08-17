<?php

namespace App\Events\Recruitment\Hiring;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ContractUpdated implements ShouldBroadcastNow
{
    public $contract;

    public function __construct($contract)
    {
        $this->contract = $contract;
    }

    public function broadcastOn()
    {
        return new Channel('contracts');
    }

    public function broadcastAs()
    {
        return 'contract.updated';
    }
}