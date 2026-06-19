<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdsOrganizationMembership extends Model
{
    protected $table = 'pds_organization_memberships';
    protected $fillable = ['employee_id', 'organization', 'order'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}