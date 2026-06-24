<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsOrganizationMembership extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $table = 'pds_organization_memberships';
    protected $fillable = [
        'employee_id',
        'organization',
        'order',
    ];

        public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}