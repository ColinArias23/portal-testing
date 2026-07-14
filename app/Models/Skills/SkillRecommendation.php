<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Skill;
 
class SkillRecommendation extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
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
 
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
