<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsChild extends Model
{

    use AutoUppercase; 

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $table = 'pds_children';
    protected $fillable = [
        'employee_id', 
        'name', 
        'date_of_birth', 
        'order'
    ];
    protected $casts = [
        'date_of_birth' => 'date:Y-m-d'
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}