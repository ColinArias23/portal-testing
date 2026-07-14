<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use App\Traits\Concerns\AutoUppercase;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SkillRecommendation;
use App\Models\DevelopmentPlan;
 
class Skill extends Model
{

    use AutoUppercase;

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'name',
        'description',
        'category',
        'weight',
        'display_order',
        'is_active',
    ];
 
    protected $casts = [
        'is_active' => 'boolean',
    ];
 
    public function recommendation(): HasOne
    {
        return $this->hasOne(SkillRecommendation::class);
    }
 
    public function developmentPlans(): HasMany
    {
        return $this->hasMany(DevelopmentPlan::class);
    }
 
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
 
    public function scopeOrderByDisplay(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
