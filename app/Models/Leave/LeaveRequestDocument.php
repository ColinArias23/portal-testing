<?php

namespace App\Models;

use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequestDocument extends Model
{
    use SoftDeletes, AutoUppercase;

    protected array $uppercaseExclude = [
        'requirement_key',
        'original_filename',
        'stored_path',
        'disk',
        'mime_type',
        'checksum_sha256',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'leave_request_id',
        'requirement_key',
        'original_filename',
        'stored_path',
        'disk',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'uploaded_by',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
