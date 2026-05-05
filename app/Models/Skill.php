<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SkillRecommendation;
use App\Models\DevelopmentPlan;
 
class Skill extends Model
{
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
 
    // ─── Relationships ────────────────────────────────────────────────────
 
    /**
     * Get the recommendation associated with this skill
     *
     * @return HasOne
     */
    public function recommendation(): HasOne
    {
        return $this->hasOne(SkillRecommendation::class);
    }
 
    /**
     * Get all development plans associated with this skill
     *
     * @return HasMany
     */
    public function developmentPlans(): HasMany
    {
        return $this->hasMany(DevelopmentPlan::class);
    }
 
    // ─── Scopes ───────────────────────────────────────────────────────────
 
    /**
     * Filter only active skills
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
 
    /**
     * Filter skills by category
     *
     * @param Builder $query
     * @param string $category
     * @return Builder
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
 
    /**
     * Order skills by display order and name
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrderByDisplay(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
