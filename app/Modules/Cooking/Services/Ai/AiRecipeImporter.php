<?php

namespace App\Modules\Cooking\Services\Ai;

use App\Modules\Cooking\Models\Ingredient;
use App\Modules\Cooking\Models\Recipe;
use App\Modules\Cooking\Support\IngredientNormalizer;
use Illuminate\Support\Facades\DB;

class AiRecipeImporter
{
    public function import(array $recipeData): ?Recipe
    {
        $name = trim($recipeData['name']);
        $normalizedName = IngredientNormalizer::normalize($name);

        $exists = Recipe::whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])->exists();
        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($recipeData, $name) {
            $recipe = Recipe::create([
                'name' => $name,
                'steps' => $recipeData['steps'],
                'servings' => $recipeData['servings'] ?? null,
                'image_url' => null,
                'source' => Recipe::SOURCE_AI,
            ]);
            foreach ($recipeData['ingredients'] as $ing) {
                $recipe->ingredients()->syncWithoutDetaching(
                    Ingredient::findOrCreateNormalized($ing)
                );
            }

            return $recipe->load('ingredients');
        });
    }

    public function importMany(array $recipes): array
    {
        $created = [];
        foreach ($recipes as $r) {
            $recipe = $this->import($r);
            if ($recipe) {
                $created[] = $recipe;
            }
        }

        return $created;
    }
}
