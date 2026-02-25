<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles;

    protected $guard_name = 'sanctum';

    protected $fillable = ['employee_id','email','password','approval_status','approved_at','approved_by'];

    protected $hidden = ['password','remember_token'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
