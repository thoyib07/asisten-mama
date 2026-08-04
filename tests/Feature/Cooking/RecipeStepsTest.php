<?php

use App\Modules\Cooking\Models\Recipe;
use App\Modules\Cooking\Support\RecipeSteps;

it('normalizes a plain-string textarea input into steps without duration', function () {
    $steps = RecipeSteps::normalize("Kocok telur\n2. Goreng di wajan panas\n\n");

    expect($steps)->toBe([
        ['text' => 'Kocok telur', 'duration_minutes' => null],
        ['text' => 'Goreng di wajan panas', 'duration_minutes' => null],
    ]);
});

it('normalizes legacy plain-string step arrays without duration', function () {
    $steps = RecipeSteps::normalize(['Kocok telur', 'Goreng di wajan panas']);

    expect($steps)->toBe([
        ['text' => 'Kocok telur', 'duration_minutes' => null],
        ['text' => 'Goreng di wajan panas', 'duration_minutes' => null],
    ]);
});

it('preserves duration_minutes from AI-shaped step objects and drops empty ones', function () {
    $steps = RecipeSteps::normalize([
        ['text' => 'Kocok telur', 'duration_minutes' => 2],
        ['text' => '  ', 'duration_minutes' => 5],
        ['text' => 'Goreng di wajan panas', 'duration_minutes' => null],
    ]);

    expect($steps)->toBe([
        ['text' => 'Kocok telur', 'duration_minutes' => 2],
        ['text' => 'Goreng di wajan panas', 'duration_minutes' => null],
    ]);
});

it('reads legacy plain-string steps stored directly in the DB as text-only entries, no backfill needed', function () {
    $recipe = Recipe::create([
        'name' => 'Resep Lama',
        'steps' => ['Langkah lama satu', 'Langkah lama dua'],
        'source' => Recipe::SOURCE_SEED,
    ]);

    // Simulate a row written before {text, duration_minutes} existed, bypassing the mutator.
    $recipe->newQuery()->whereKey($recipe->id)->update([
        'steps' => json_encode(['Langkah lama satu', 'Langkah lama dua']),
    ]);

    $fresh = $recipe->fresh();

    expect($fresh->steps)->toBe([
        ['text' => 'Langkah lama satu', 'duration_minutes' => null],
        ['text' => 'Langkah lama dua', 'duration_minutes' => null],
    ]);
});

it('round-trips duration_minutes and nutrition through the Recipe model', function () {
    $recipe = Recipe::create([
        'name' => 'Resep Gizi',
        'steps' => [['text' => 'Masak', 'duration_minutes' => 10]],
        'source' => Recipe::SOURCE_SEED,
        'duration_minutes' => 25,
        'nutrition' => ['calories' => 300, 'protein' => 20, 'carbs' => 30, 'fat' => 10],
    ]);

    $fresh = $recipe->fresh();

    expect($fresh->duration_minutes)->toBe(25);
    expect($fresh->nutrition)->toBe(['calories' => 300, 'protein' => 20, 'carbs' => 30, 'fat' => 10]);
    expect($fresh->steps)->toBe([['text' => 'Masak', 'duration_minutes' => 10]]);
});
