<?php

namespace App\Http\Resources\Leave;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'leave_type' => [
                'id' => $this->leaveType->id,
                'code' => $this->leaveType->code,
                'name' => $this->leaveType->name,
                'color' => $this->leaveType->color,
                'unit' => $this->leaveType->unit ?? 'days',
            ],
            'year' => $this->year,
            'opening_balance' => (float) $this->opening_balance,
            'carry_forward_in' => (float) $this->carry_forward_in,
            'earned' => (float) $this->earned,
            'used' => (float) $this->used,
            'adjusted' => (float) $this->adjusted,
            'monetized' => (float) $this->monetized,
            'available' => (float) $this->available,
        ];
    }
}