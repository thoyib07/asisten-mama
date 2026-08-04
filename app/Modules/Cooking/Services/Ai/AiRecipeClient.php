<?php

namespace App\Modules\Cooking\Services\Ai;

interface AiRecipeClient
{
    /**
     * @param  array<int, string>  $ingredientNames
     * @param  array<int, string>  $mealCategories
     * @param  array<int, string>  $cuisineTypes
     * @return array<int, array{
     *     name: string,
     *     ingredients: array<int, array{name: string, is_primary: bool, quantity: ?string}>,
     *     steps: array<int, array{text: string, duration_minutes: ?int}>,
     *     servings: ?int,
     *     duration_minutes: ?int,
     *     nutrition: ?array{calories: ?int, protein: ?int, carbs: ?int, fat: ?int},
     * }>
     */
    public function suggest(array $ingredientNames, array $mealCategories = [], array $cuisineTypes = []): array;
}
