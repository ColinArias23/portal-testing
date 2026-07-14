<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
 
class DevelopmentPlan extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'employee_id',
        'skill_assessment_id',
        'skill_id',
        'goal',
        'actions',
        'resources',
        'start_date',
        'target_date',
        'status',
        'progress_percentage',
        'notes',
        'completed_at',
    ];
 
    protected $casts = [
        'actions'      => 'array',
        'resources'    => 'array',
        'start_date'   => 'date',
        'target_date'  => 'date',
        'completed_at' => 'datetime',
    ];
 
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
 
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(SkillAssessment::class, 'skill_assessment_id');
    }
 
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
 
    public function scopeByEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }
 
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['planned', 'in_progress']);
    }
 
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
 
    public function markAsCompleted(): bool
    {
        $this->status = 'completed';
        $this->progress_percentage = 100;
        $this->completed_at = Carbon::now();
        return $this->save();
    }
 
    public function updateProgress(int $percentage): bool
    {
        $this->progress_percentage = min(max($percentage, 0), 100);
        
        if ($percentage >= 100) {
            return $this->markAsCompleted();
        }
        
        return $this->save();
    }
}