<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsEducation extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $table = 'pds_education';
    protected $fillable = [
        'employee_id', 
        'level',
        'school', 
        'degree', 
        'period_from', 
        'period_to',
        'highest_level', 
        'year_graduated', 
        'honors',
        'order',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}