<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
  protected $fillable = ['code','name','description'];

  public function orgUnits()
  {
    return $this->hasMany(OrgUnit::class);
  }
}
