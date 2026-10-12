<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaPositionHistory extends Model
{
    public const ASSIGNED = 'ASSIGNED';
    public const VACATED = 'VACATED';
    public const TRANSFERRED = 'TRANSFERRED';
    public const PROMOTED = 'PROMOTED';

    protected $table = 'plantilla_position_histories';

    protected $fillable = [
        'plantilla_position_id',
        'position_label',
        'employee_id',
        'employee_name',
        'event_type',
        'related_position_id',
        'related_position_label',
        'plantilla_posting_application_id',
        'employee_assignment_id',
        'effective_date',
        'remarks',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function position()
    {
        return $this->belongsTo(PlantillaPosition::class, 'plantilla_position_id');
    }

    public function relatedPosition()
    {
        return $this->belongsTo(PlantillaPosition::class, 'related_position_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignment()
    {
        return $this->belongsTo(EmployeeAssignment::class, 'employee_assignment_id');
    }

    public function application()
    {
        return $this->belongsTo(PlantillaPostingApplication::class, 'plantilla_posting_application_id');
    }
}
