<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaItem extends Model
{
  protected $fillable = [
    'item_number',
    'org_unit_id',
    'position_id',
    'funding_source',
    'item_status',
    'employee_id',
    'filled_at',
    'actual_monthly_salary',
    'salary_override_reason',
  ];

  protected $casts = [
    'filled_at' => 'date',
    'actual_monthly_salary' => 'decimal:2',
  ];

  public function orgUnit()
  {
    return $this->belongsTo(OrgUnit::class, 'org_unit_id');
  }

  public function position()
  {
    return $this->belongsTo(Position::class, 'position_id');
  }

  public function employee()
  {
    return $this->belongsTo(Employee::class, 'employee_id');
  }
}
