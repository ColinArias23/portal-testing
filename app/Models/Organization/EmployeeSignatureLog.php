<?php

namespace App\Models;

use App\Enums\SignatureLogAction;
use App\Enums\SignatureType;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSignatureLog extends Model
{
    use AutoUppercase;

    public const UPDATED_AT = null;

    protected array $uppercaseExclude = [
        'signature_type',
        'action',
        'original_filename',
        'created_at',
    ];

    protected $fillable = [
        'employee_id',
        'actor_id',
        'signature_type',
        'action',
        'original_filename',
    ];

    protected $casts = [
        'signature_type' => SignatureType::class,
        'action' => SignatureLogAction::class,
        'created_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
