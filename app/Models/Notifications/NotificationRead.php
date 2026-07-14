<?php

namespace App\Models;

use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Model;

class NotificationRead extends Model
{
    use AutoUppercase;

    public $timestamps = false;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'notification_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}