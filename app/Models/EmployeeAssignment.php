<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'plantilla_item_id',
        'is_primary',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_primary' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function plantillaItem()
    {
        return $this->belongsTo(PlantillaItem::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }
}