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
        // Check info fields
        $infoFilled = collect(self::REQUIRED_FIELDS)
            ->filter(fn($field) => !empty($this->$field))
            ->count();

        $employee     = $this->employee;
        $nameFilled   = !empty($employee?->first_name) && !empty($employee?->last_name);
        $avatarFilled = !empty($employee?->avatar_url);

        $totalRequired = count(self::REQUIRED_FIELDS) + 3; 
        $totalFilled   = $infoFilled
            + ($nameFilled ? 2 : (
                (!empty($employee?->first_name) ? 1 : 0) +
                (!empty($employee?->last_name)  ? 1 : 0)
            ))
            + ($avatarFilled ? 1 : 0);

        if ($totalFilled === 0)              return 'EMPTY';
        if ($totalFilled === $totalRequired) return 'COMPLETE';
        return 'INCOMPLETE';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}