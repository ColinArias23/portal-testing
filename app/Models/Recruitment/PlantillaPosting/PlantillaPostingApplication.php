<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlantillaPostingApplication extends Model
{
    use HasFactory;

    public const STATUS_POSTING_CANCELLED = 'Posting Cancelled';

    protected $fillable = [
        'plantilla_posting_id',
        'employee_id',
        'notes',
        'certified',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'remarks',
    ];

    protected $casts = [
        'certified' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function posting()
    {
        return $this->belongsTo(PlantillaPosting::class, 'plantilla_posting_id')->withTrashed();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function documents()
    {
        return $this->hasMany(PlantillaPostingApplicationDocument::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function interview(): HasOne
    {
        return $this->hasOne(PlantillaPostingApplicationInterview::class);
    }
}