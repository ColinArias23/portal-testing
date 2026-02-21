<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'employee_number',
        'plantilla_item_id',
        'sg_level',
        'step_increment_id',
        'prefix',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'title',
        'position_designation',
        'role',
        'employment_type',
        'employment_status',
        'avatar',
        'border_color',
        'aligned',
        'expanded',
        'notes',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'division_employee')
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }

        public function plantillaItem()
    {
        return $this->belongsTo(PlantillaItem::class, 'plantilla_item_id');
    }
}
