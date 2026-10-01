<?php

namespace App\Models\Dtr;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DtrFace extends Model
{
    protected $table = 'dtr_faces';

    protected $fillable = ['employee_id', 'encodings', 'image_path', 'registered_by', 'legacy_id'];

    protected $hidden = ['encodings'];

    protected $casts = [
        'encodings' => 'encrypted:array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
