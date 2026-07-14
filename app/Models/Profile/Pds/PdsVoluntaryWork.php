<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsVoluntaryWork extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $table = 'pds_voluntary_works';
    protected $fillable = [
        'employee_id',
        'org_name_address', 
        'date_from', 
        'date_to', 
        'hours', 
        'position',
        'order',
    ];
    protected $casts = [
        'date_from' => 'date:Y-m-d',
        'date_to'   => 'date:Y-m-d',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}