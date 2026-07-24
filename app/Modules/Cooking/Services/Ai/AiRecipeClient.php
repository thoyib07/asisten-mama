<?php

namespace App\Modules\Cooking\Services\Ai;

interface AiRecipeClient
{
    /**
     * @param  array<int, string>  $ingredientNames
     * @return array<int, array{name: string, ingredients: array<int, string>, steps: array<int, string>, servings: ?int}>
     */
    public function suggest(array $ingredientNames): array;
}
