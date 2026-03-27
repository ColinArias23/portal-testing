<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeInfo extends Model
{
    protected $table = 'employee_info';

    protected $fillable = [
        'employee_id',
        'contact',
        'address',
        'birthdate',
        'gender',
        'status',
    ];

    private const REQUIRED_FIELDS = ['contact', 'address', 'birthdate', 'gender'];

    protected static function booted(): void
    {
        static::saving(function (EmployeeInfo $info) {
            $info->status = $info->computeStatus();
        });
    }

    public function computeStatus(): string
    {
        $infoFilled = collect(self::REQUIRED_FIELDS)
            ->filter(fn($field) => !empty($this->$field))
            ->count();

        $employee = $this->employee;

        $firstNameFilled = !empty($employee?->first_name) ? 1 : 0;
        $lastNameFilled  = !empty($employee?->last_name)  ? 1 : 0;

        // Check avatar the same way ProfileController does — via file_exists
        $rawAvatarPath = $employee?->getRawOriginal('avatar_url');
        $avatarFilled  = ($rawAvatarPath && file_exists(storage_path('app/public/' . $rawAvatarPath))) ? 1 : 0;

        $totalRequired = count(self::REQUIRED_FIELDS) + 3; // contact, address, birthdate, gender + first_name, last_name, avatar
        $totalFilled   = $infoFilled + $firstNameFilled + $lastNameFilled + $avatarFilled;

        if ($totalFilled === 0)              return 'EMPTY';
        if ($totalFilled === $totalRequired) return 'COMPLETE';
        return 'INCOMPLETE';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}