<?php

namespace App\Modules\Cooking\Services\Matching;

use App\Modules\Cooking\Models\Recipe;
use App\Modules\Cooking\Support\IngredientNormalizer;

class RecipeMatcher
{
    public function search(array $rawIngredientNames): array
    {
        $have = collect($rawIngredientNames)
            ->map(fn ($n) => IngredientNormalizer::normalize($n))
            ->filter()
            ->unique()
            ->values();

        if ($have->isEmpty()) {
            return [];
        }

        $results = [];
        $recipes = Recipe::with('ingredients')->has('ingredients')->get();

        foreach ($recipes as $recipe) {
            $names = $recipe->ingredients->pluck('name');
            $total = $names->count();
            $matched = $names->filter(fn ($n) => $have->contains($n))->values();
            $missing = $names->reject(fn ($n) => $have->contains($n))->values();
            $score = $total > 0 ? round($matched->count() / $total, 4) : 0.0;

            $results[] = new MatchResult($recipe, $score, $matched->all(), $missing->all());
        }

        usort($results, fn ($a, $b) => $b->score <=> $a->score ?: count($a->missing) <=> count($b->missing));

        return $results;
    }
}
