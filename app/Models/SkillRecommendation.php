<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Skill;
 
class SkillRecommendation extends Model
{
    protected $fillable = [
        'skill_id',
        'actions',
        'resources',
        'manager_support',
        'timeline',
    ];
 
    protected $casts = [
        'actions'         => 'array',
        'resources'       => 'array',
        'manager_support' => 'array',
    ];
 
    // ─── Relationships ────────────────────────────────────────────────────
 
    /**
     * Get the skill associated with this recommendation
     *
     * @return BelongsTo
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
