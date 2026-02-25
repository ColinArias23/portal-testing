<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'division_id',
        'parent_id',
        'type',
        'code',
        'name',
        'description',
        'head_employee_id',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function plantillaItems()
    {
        return $this->hasMany(PlantillaItem::class);
    }
}