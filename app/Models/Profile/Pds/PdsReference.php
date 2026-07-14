<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsReference extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $table = 'pds_references';
    protected $fillable = [
        'employee_id',
        'name',
        'address',
        'contact',
        'order',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}