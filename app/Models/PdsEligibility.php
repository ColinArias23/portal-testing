<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsEligibility extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $table = 'pds_eligibilities';
    protected $fillable = [
        'employee_id',
        'eligibility', 
        'rating', 
        'exam_date', 
        'place', 
        'license_no', 
        'valid_until',
        'order',
    ];
    protected $casts = [
        'exam_date'   => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}