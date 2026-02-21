<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'head_employee_id',
    ];

    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function divisions()
    {
        return $this->hasMany(Division::class);
    }
}
