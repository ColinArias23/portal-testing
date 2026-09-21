<?php

namespace App\Models;

use App\Traits\Concerns\AutoUppercase;
use App\Traits\Concerns\ResolvesAvatarUrl;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, SoftDeletes, AutoUppercase, ResolvesAvatarUrl;

    protected $guard_name = 'web';

    protected array $uppercaseExclude = [
        'email',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = ['name', 'avatar_url'];

    protected $fillable = [
        'employee_id',
        'username',
        'email',
        'password',
        'has_completed_orientation',
        'approval_status',
        'approved_at',
        'approved_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'has_completed_orientation' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            $user->tokens()->delete();
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->with('primaryDepartmentAssignment.department');
    }

    public function getNameAttribute(): string
    {
        return $this->employee?->full_name ?: $this->username;
    }

    public function getFormalNameAttribute(): string
    {
        return $this->employee?->formal_name ?? $this->name;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->relationLoaded('employee') && ! $this->employee_id) {
            return null;
        }

        return $this->resolveAvatarUrl(
            $this->employee?->getRawOriginal('avatar_url')
        );
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