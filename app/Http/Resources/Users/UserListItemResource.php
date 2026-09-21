<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->employee;

        return [
            'id'            => $this->id,
            'username'      => $this->username,
            'email'         => $this->email,
            'first_name'    => $employee?->first_name,
            'middle_name'   => $employee?->middle_name,
            'last_name'     => $employee?->last_name,
            'name'          => $employee?->full_name ?: $this->username,
            'role_position' => $employee?->role_position ?? [],
            'approved_at'   => $this->approved_at,
            'roles'         => $this->roles->pluck('name')->values(),
            'permissions'   => $this->permissions->pluck('name')->values(),
        ];
    }
}