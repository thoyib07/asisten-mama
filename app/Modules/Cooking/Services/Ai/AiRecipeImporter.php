<?php

namespace App\Modules\Cooking\Services\Ai;

use App\Modules\Cooking\Models\Ingredient;
use App\Modules\Cooking\Models\Recipe;
use App\Modules\Cooking\Support\IngredientNormalizer;
use Illuminate\Support\Facades\DB;

class AiRecipeImporter
{
    public function import(array $recipeData, array $mealCategories = [], array $cuisineTypes = []): ?Recipe
    {
        $name = trim($recipeData['name']);
        $normalizedName = IngredientNormalizer::normalize($name);

        $exists = Recipe::whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])->exists();
        if ($exists) {
            return null;
        }

        // A recipe only carries one cuisine_type (§6.2 — single value per recipe).
        // When the user filtered on more than one, which one applies is ambiguous,
        // so leave it null rather than guessing.
        $cuisineType = count($cuisineTypes) === 1 ? $cuisineTypes[0] : null;

        return DB::transaction(function () use ($recipeData, $name, $mealCategories, $cuisineType) {
            $recipe = Recipe::create([
                'name' => $name,
                'steps' => $recipeData['steps'],
                'servings' => $recipeData['servings'] ?? null,
                'duration_minutes' => $recipeData['duration_minutes'] ?? null,
                'nutrition' => $recipeData['nutrition'] ?? null,
                'image_url' => null,
                'source' => Recipe::SOURCE_AI,
                'meal_categories' => $mealCategories !== [] ? $mealCategories : null,
                'cuisine_type' => $cuisineType,
            ]);
            foreach ($recipeData['ingredients'] as $ing) {
                $ingredient = Ingredient::findOrCreateNormalized($ing['name']);
                $recipe->ingredients()->syncWithoutDetaching([
                    $ingredient->id => [
                        'is_primary' => $ing['is_primary'] ?? false,
                        'quantity' => $ing['quantity'] ?? null,
                    ],
                ]);
            }

            return $recipe->load('ingredients');
        });
    }

    public function importMany(array $recipes, array $mealCategories = [], array $cuisineTypes = []): array
    {
        $created = [];
        foreach ($recipes as $r) {
            $recipe = $this->import($r, $mealCategories, $cuisineTypes);
            if ($recipe) {
                $created[] = $recipe;
            }
        }

        return $created;
    }
}
