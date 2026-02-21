<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantillaItem extends Model
{
    protected $fillable = [
        'item_number',
        'status',
        'salary_grade_id',
        'title',
        'description',
    ];

    public function employee()
    {
        return $this->hasOne(Employee::class, 'plantilla_item_id');
    }

    public function salaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class);
    }
}