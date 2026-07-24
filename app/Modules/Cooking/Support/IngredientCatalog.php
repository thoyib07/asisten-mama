<?php

namespace App\Modules\Cooking\Support;

use App\Modules\Cooking\Models\Ingredient;

class IngredientCatalog
{
    private const MAX_TYPO_DISTANCE = 1;

    private const MIN_LENGTH_FOR_TYPO_TOLERANCE = 4;

    /**
     * Known-ingredient check: matches the existing ingredients table
     * (grows over time via seed/admin/AI imports) or a curated static list
     * of common Indonesian/international ingredient names, with a small
     * typo tolerance for near-misses.
     */
    public static function isKnown(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }

        if (Ingredient::where('name', $normalized)->exists()) {
            return true;
        }

        foreach (self::commonNames() as $known) {
            if (self::isCloseMatch($normalized, $known)) {
                return true;
            }
        }

        return false;
    }

    private static function isCloseMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (mb_strlen($a) < self::MIN_LENGTH_FOR_TYPO_TOLERANCE || mb_strlen($b) < self::MIN_LENGTH_FOR_TYPO_TOLERANCE) {
            return false;
        }

        return levenshtein($a, $b) <= self::MAX_TYPO_DISTANCE;
    }

    /** @return array<int, string> */
    public static function commonNames(): array
    {
        return require base_path('resources/data/common_ingredients.php');
    }
}
