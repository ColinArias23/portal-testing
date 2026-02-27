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

    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class, 'plantilla_item_id');
    }

    public function activeAssignments()
    {
        return $this->assignments()->whereNull('end_date');
    }

    public function isFilled(): bool
    {
        return $this->activeAssignments()->exists();
    }

    public function isOverstaffed(): bool
    {
        return $this->activeAssignments()->count() > 1;
    }
}
