<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgUnit extends Model
{
  protected $table = 'org_units';

  protected $fillable = [
    'division_id','parent_id','code','name','type','description',
  ];

  public function division()
  {
    return $this->belongsTo(Division::class);
  }

  public function parent()
  {
    return $this->belongsTo(OrgUnit::class, 'parent_id');
  }

  public function children()
  {
    return $this->hasMany(OrgUnit::class, 'parent_id');
  }

  public function plantillaItems()
  {
    return $this->hasMany(PlantillaItem::class, 'org_unit_id');
  }
}
