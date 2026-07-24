<?php

namespace App\Modules\ShoppingList\Services;

use App\Models\Household;
use App\Modules\Cooking\Support\IngredientNormalizer;
use App\Modules\ShoppingList\Models\ShoppingList;

class MissingIngredientsToShoppingList
{
    /**
     * @param  array<int, string>  $missing  raw ingredient names, e.g. MatchResult::$missing
     */
    public function add(Household $household, array $missing): ShoppingList
    {
        $list = ShoppingList::firstOrCreate(['household_id' => $household->id]);

        foreach ($missing as $raw) {
            $name = IngredientNormalizer::normalize($raw);
            if ($name === '') {
                continue;
            }

            $list->items()->firstOrCreate([
                'name' => $name,
            ], [
                'added_by' => auth()->id(),
            ]);
        }

        return $list;
    }
}
