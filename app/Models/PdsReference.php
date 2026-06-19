<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsReference extends Model
{
    protected $table = 'pds_references';
    protected $fillable = ['employee_id', 'name', 'address', 'contact', 'order'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}