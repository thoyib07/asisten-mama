<?php

namespace App\Modules\Cooking\Services\Matching;

use App\Modules\Cooking\Models\Recipe;
use App\Modules\Cooking\Support\IngredientNormalizer;

class RecipeMatcher
{
    private const PRIMARY_WEIGHT = 2;

    private const SECONDARY_WEIGHT = 1;

    public function search(array $rawIngredientNames, array $mealCategories = [], array $cuisineTypes = []): array
    {
        $have = collect($rawIngredientNames)
            ->map(fn ($n) => IngredientNormalizer::normalize($n))
            ->filter()
            ->unique()
            ->values();

        if ($have->isEmpty()) {
            return [];
        }

        // Hanya resep yang punya minimal satu bahan yang dimiliki user. Tanpa pra-filter ini
        // seluruh katalog (global, tumbuh terus tiap impor AI) dimuat dan dinilai di PHP tiap
        // pencarian, lalu ikut terkirim bolak-balik lewat snapshot Livewire karena
        // RecipeFinder::$results itu properti public.
        $query = Recipe::with('ingredients')
            ->whereHas('ingredients', fn ($q) => $q->whereIn('name', $have->all()));

        if ($mealCategories !== []) {
            $query->where(function ($q) use ($mealCategories) {
                foreach ($mealCategories as $category) {
                    $q->orWhereJsonContains('meal_categories', $category);
                }
            });
        }

        if ($cuisineTypes !== []) {
            $query->whereIn('cuisine_type', $cuisineTypes);
        }

        $results = [];

        foreach ($query->get() as $recipe) {
            $ingredients = $recipe->ingredients;
            $totalWeight = 0;
            $matchedWeight = 0;
            $matched = [];
            $missing = [];

            foreach ($ingredients as $ingredient) {
                $weight = $ingredient->pivot->is_primary ? self::PRIMARY_WEIGHT : self::SECONDARY_WEIGHT;
                $totalWeight += $weight;

                if ($have->contains($ingredient->name)) {
                    $matchedWeight += $weight;
                    $matched[] = $ingredient->name;
                } else {
                    $missing[] = $ingredient->name;
                }
            }

            $score = $totalWeight > 0 ? round($matchedWeight / $totalWeight, 4) : 0.0;

            if ($score <= 0) {
                continue;
            }

            $results[] = new MatchResult($recipe, $score, $matched, $missing);
        }

        usort($results, fn ($a, $b) => $b->score <=> $a->score ?: count($a->missing) <=> count($b->missing));

        return $results;
    }
}
