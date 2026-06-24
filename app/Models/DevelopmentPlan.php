<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;
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
 
    // ─── Relationships ────────────────────────────────────────────────────
 
    /**
     * Get the employee associated with this development plan
     *
     * @return BelongsTo
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
 
    /**
     * Get the skill assessment associated with this plan
     *
     * @return BelongsTo
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(SkillAssessment::class, 'skill_assessment_id');
    }
 
    /**
     * Get the skill associated with this development plan
     *
     * @return BelongsTo
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
 
    // ─── Scopes ───────────────────────────────────────────────────────────
 
    /**
     * Filter development plans by employee
     *
     * @param Builder $query
     * @param int $employeeId
     * @return Builder
     */
    public function scopeByEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }
 
    /**
     * Filter only active development plans (planned or in_progress)
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['planned', 'in_progress']);
    }
 
    /**
     * Filter only completed development plans
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
 
    // ─── Methods ───────────────────────────────────────────────────────────
 
    /**
     * Mark the development plan as completed
     *
     * @return bool
     */
    public function markAsCompleted(): bool
    {
        $this->status = 'completed';
        $this->progress_percentage = 100;
        $this->completed_at = Carbon::now();
        return $this->save();
    }
 
    /**
     * Update the progress percentage of the development plan
     * Auto-completes when progress reaches 100%
     *
     * @param int $percentage
     * @return bool
     */
    public function updateProgress(int $percentage): bool
    {
        $this->progress_percentage = min(max($percentage, 0), 100);
        
        if ($percentage >= 100) {
            return $this->markAsCompleted();
        }
        
        return $this->save();
    }
}