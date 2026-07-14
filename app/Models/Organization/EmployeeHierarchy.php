<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHierarchy extends Model
{

    use AutoUppercase;
    protected $table = 'employee_hierarchy';

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'employee_id',
        'parent_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'parent_id');
    }
}