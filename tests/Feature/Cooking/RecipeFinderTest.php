<?php

use App\Modules\Cooking\Livewire\RecipeFinder;
use App\Modules\Cooking\Models\Ingredient;
use App\Modules\Cooking\Models\Recipe;
use Livewire\Livewire;

it('excludes untagged recipes end-to-end when a meal category filter is selected via Livewire', function () {
    $tagged = Recipe::create([
        'name' => 'Nasi Goreng Sarapan',
        'steps' => ['Langkah 1'],
        'source' => Recipe::SOURCE_SEED,
        'meal_categories' => ['sarapan'],
    ]);
    $tagged->ingredients()->attach(Ingredient::firstOrCreate(['name' => 'nasi'])->id, ['is_primary' => false]);

    $untagged = Recipe::create([
        'name' => 'Nasi Goreng Polos',
        'steps' => ['Langkah 1'],
        'source' => Recipe::SOURCE_SEED,
    ]);
    $untagged->ingredients()->attach(Ingredient::firstOrCreate(['name' => 'nasi'])->id, ['is_primary' => false]);

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['nasi'])
        ->set('selectedMealCategories', ['sarapan'])
        ->call('search')
        ->assertSet('results.0.id', $tagged->id)
        ->assertCount('results', 1);
});
