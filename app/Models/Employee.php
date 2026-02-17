<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
  protected $fillable = [
    'employee_number',
    'first_name','middle_name','last_name','suffix',
    'sex','birthdate','civil_status','blood_type','citizenship',
    'email','contact_no',
    'region','city','barangay','zipcode',
    'employment_status',
  ];

  protected $casts = [
    'birthdate' => 'date',
  ];

  public function getFullNameAttribute(): string
  {
    $mid = $this->middle_name ? (' ' . $this->middle_name) : '';
    $suf = $this->suffix ? (' ' . $this->suffix) : '';
    return "{$this->first_name}{$mid} {$this->last_name}{$suf}";
  }

  public function users()
  {
    return $this->hasMany(User::class);
  }
}
