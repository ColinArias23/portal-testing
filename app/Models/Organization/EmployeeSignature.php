<?php

namespace App\Models;

use App\Enums\SignatureType;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSignature extends Model
{
    use AutoUppercase;

    protected array $uppercaseExclude = [
        'type',
        'original_filename',
        'stored_path',
        'disk',
        'mime_type',
        'checksum_sha256',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'employee_id',
        'type',
        'original_filename',
        'stored_path',
        'disk',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'uploaded_by',
    ];

    protected $casts = [
        'type' => SignatureType::class,
        'size_bytes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
