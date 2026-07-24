<?php

namespace App\Modules\Cooking\Models;

use App\Modules\Cooking\Support\IngredientNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    protected $fillable = ['name'];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredient');
    }

    public static function findOrCreateNormalized(string $raw): self
    {
        $name = IngredientNormalizer::normalize($raw);

        return static::firstOrCreate(['name' => $name]);
    }
}
