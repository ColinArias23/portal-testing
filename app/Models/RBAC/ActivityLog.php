<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;

class ActivityLog extends Model
{
    use AutoUppercase;

    public $timestamps = false;

    protected array $uppercaseExclude = [
        'performed_at',
    ];
    
    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'payload',
        'ip_address',
        'user_agent',
        'performed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'performed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string  $action,
        ?int    $userId       = null,
        ?string $subjectType  = null,
        ?int    $subjectId    = null,
        ?string $subjectLabel = null,
        array   $payload      = [],
        ?string $ip           = null,
        ?string $userAgent    = null,
    ): self {
        return self::create([
            'user_id'       => $userId,
            'action'        => $action,
            'subject_type'  => $subjectType,
            'subject_id'    => $subjectId,
            'subject_label' => $subjectLabel,
            'payload'       => $payload ?: null,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
            'performed_at'  => now(),
        ]);
    }
}