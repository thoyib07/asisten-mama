<?php

use App\Modules\Cooking\Livewire\RecipeRating;
use App\Modules\Cooking\Models\Rating;
use App\Modules\Cooking\Models\Recipe;
use Livewire\Livewire;

it('updates the existing rating in place instead of inserting a new row', function () {
    $user = makeHouseholdUser('Hasan');
    auth()->login($user);

    $recipe = Recipe::create([
        'name' => 'Soto Ayam',
        'steps' => ['Langkah 1'],
        'source' => Recipe::SOURCE_SEED,
    ]);

    Livewire::test(RecipeRating::class, ['recipe' => $recipe])
        ->call('rate', 3)
        ->call('rate', 5);

    expect(Rating::where('recipe_id', $recipe->id)->where('user_id', $user->id)->count())->toBe(1);
    expect(Rating::where('recipe_id', $recipe->id)->where('user_id', $user->id)->first()->value)->toBe(5);
});
