<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsNonAcademicDistinction extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $table = 'pds_non_academic_distinctions';
    protected $fillable = [
        'employee_id', 
        'distinction', 
        'order',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}