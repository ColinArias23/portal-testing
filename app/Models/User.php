<?php

namespace App\Models;

use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, softDeletes, AutoUppercase;

    protected $guard_name = 'web';

    protected array $uppercaseExclude = [
        'email',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
    protected $fillable = [
        'employee_id', 
        'username', 
        'given_name',
        'middle_name',
        'last_name',   
        'email', 
        'password', 
        'has_completed_orientation',
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'has_completed_orientation' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'APPROVED');
    }

    public function scopePending($query)
    {
        return $query->where('approval_status', 'PENDING');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'APPROVED';
    }
}