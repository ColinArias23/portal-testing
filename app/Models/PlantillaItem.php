<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaItem extends Model
{
    protected $fillable = [
        'department_id',
        'division_id',
        'item_number',
        'status',
        'salary_grade_id',
        'title',
        'description',
    ];

    public function salaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function isOverstaffed(): bool
    {
        return $this->assignments()->active()->count() > 1;
    }
}
