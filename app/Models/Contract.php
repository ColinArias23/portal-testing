<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'applicant_id', 'employee_name', 'position',
        'salary', 'start_date', 'status', 'file_path',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'salary'     => 'decimal:2',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}