<?php

namespace App\Models;

use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'code',
        'name',
        'description',
        'head_employee_id',
        'parent_id',
        'type',
    ];

    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = $value !== null ? strtoupper($value) : $value;
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value !== null ? strtoupper($value) : $value;
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

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