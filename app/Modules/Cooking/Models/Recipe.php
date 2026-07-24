<?php

namespace App\Modules\Cooking\Models;

use App\Modules\Cooking\Support\RecipeSteps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    public const SOURCE_SEED = 'seed';

    public const SOURCE_AI = 'ai';

    protected $fillable = ['name', 'steps', 'image_url', 'source', 'servings'];

    protected function steps(): Attribute
    {
        // every write path (Filament string, AI array, seeder) normalizes here
        return Attribute::make(
            get: fn ($value) => json_decode($value ?? '[]', true) ?: [],
            set: fn ($value) => json_encode(RecipeSteps::normalize($value)),
        );
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredient')->withPivot('quantity');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function scopeFavoritedBy(Builder $query, int $userId): void
    {
        $query->whereHas('favorites', fn ($q) => $q->where('user_id', $userId));
    }
}
