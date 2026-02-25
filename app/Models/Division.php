<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'head_employee_id',
        'parent_id',
    ];

    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function parent()
    {
        return $this->belongsTo(Division::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Division::class, 'parent_id');
    }

    public function plantillaItems()
    {
        return $this->hasMany(PlantillaItem::class);
    }
}
