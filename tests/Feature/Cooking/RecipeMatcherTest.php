<?php

use App\Modules\Cooking\Models\Ingredient;
use App\Modules\Cooking\Models\Recipe;
use App\Modules\Cooking\Services\Matching\RecipeMatcher;

function makeRecipeWithIngredients(string $name, array $ingredientNames, array $primaryNames = [], array $recipeAttrs = []): Recipe
{
    $recipe = Recipe::create(array_merge([
        'name' => $name,
        'steps' => ['Langkah 1'],
        'source' => Recipe::SOURCE_SEED,
    ], $recipeAttrs));

    foreach ($ingredientNames as $ingredientName) {
        $ingredient = Ingredient::firstOrCreate(['name' => $ingredientName]);
        $recipe->ingredients()->attach($ingredient->id, ['is_primary' => in_array($ingredientName, $primaryNames, true)]);
    }

    return $recipe;
}

it('weighs primary ingredients higher so a matching primary outranks a matching secondary', function () {
    // Recipe A: "telur" is primary (weight 2) + "garam" secondary (weight 1) -> total weight 3
    $recipeA = makeRecipeWithIngredients('Telur Dadar', ['telur', 'garam'], primaryNames: ['telur']);
    // Recipe B: same ingredient count, but "telur" is secondary -> total weight also 3 (1+1+1)
    $recipeB = makeRecipeWithIngredients('Sup Sayur', ['telur', 'garam', 'gula']);

    $results = (new RecipeMatcher)->search(['telur']);

    expect($results)->toHaveCount(2);
    expect($results[0]->recipe->id)->toBe($recipeA->id);
    expect($results[0]->score)->toBe(round(2 / 3, 4));
    expect($results[1]->recipe->id)->toBe($recipeB->id);
    expect($results[1]->score)->toBe(round(1 / 3, 4));
});

it('excludes recipes that do not match the selected meal category filter', function () {
    $tagged = makeRecipeWithIngredients('Nasi Goreng Sarapan', ['nasi'], recipeAttrs: ['meal_categories' => ['sarapan']]);
    $untagged = makeRecipeWithIngredients('Nasi Goreng Polos', ['nasi']);
    $otherCategory = makeRecipeWithIngredients('Nasi Goreng Malam', ['nasi'], recipeAttrs: ['meal_categories' => ['makan_malam']]);

    $results = (new RecipeMatcher)->search(['nasi'], mealCategories: ['sarapan']);

    $ids = collect($results)->map(fn ($r) => $r->recipe->id);
    expect($ids)->toContain($tagged->id);
    expect($ids)->not->toContain($untagged->id);
    expect($ids)->not->toContain($otherCategory->id);
});

it('matches recipes against any of several selected cuisine types (OR, not AND)', function () {
    $indo = makeRecipeWithIngredients('Rendang', ['daging'], recipeAttrs: ['cuisine_type' => 'indonesia']);
    $jepang = makeRecipeWithIngredients('Sushi', ['nasi', 'daging'], recipeAttrs: ['cuisine_type' => 'jepang']);
    $barat = makeRecipeWithIngredients('Steak', ['daging'], recipeAttrs: ['cuisine_type' => 'barat']);
    $untagged = makeRecipeWithIngredients('Daging Polos', ['daging']);

    $results = (new RecipeMatcher)->search(['daging'], cuisineTypes: ['indonesia', 'jepang']);

    $ids = collect($results)->map(fn ($r) => $r->recipe->id);
    expect($ids)->toContain($indo->id);
    expect($ids)->toContain($jepang->id);
    expect($ids)->not->toContain($barat->id);
    expect($ids)->not->toContain($untagged->id);
});
