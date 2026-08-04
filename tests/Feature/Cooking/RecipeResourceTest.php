<?php

use App\Modules\Cooking\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Modules\Cooking\Models\Ingredient;
use App\Modules\Cooking\Models\Recipe;
use Livewire\Livewire;

it('lets an admin create a recipe and tag a primary ingredient via the reactive select', function () {
    $user = makeHouseholdUser('Indra');
    auth()->login($user);

    $telur = Ingredient::firstOrCreate(['name' => 'telur']);
    $garam = Ingredient::firstOrCreate(['name' => 'garam']);

    Livewire::test(CreateRecipe::class)
        ->fillForm([
            'name' => 'Telur Dadar',
            'steps' => "Kocok telur\nGoreng",
            'source' => Recipe::SOURCE_SEED,
            'ingredients' => [$telur->id, $garam->id],
            'duration_minutes' => 15,
            'nutrition' => ['calories' => 200, 'protein' => 12, 'carbs' => 2, 'fat' => 14],
        ])
        ->set('data.primary_ingredient_ids', [$telur->id])
        ->set("data.ingredient_quantities.{$telur->id}", '2 butir')
        ->set("data.ingredient_quantities.{$garam->id}", '1 sdt')
        ->call('create')
        ->assertHasNoFormErrors();

    $recipe = Recipe::where('name', 'Telur Dadar')->firstOrFail();
    expect($recipe->ingredients()->wherePivot('is_primary', true)->pluck('ingredients.id')->all())->toBe([$telur->id]);
    expect($recipe->ingredients()->wherePivot('is_primary', false)->pluck('ingredients.id')->all())->toBe([$garam->id]);
    expect($recipe->duration_minutes)->toBe(15);
    expect($recipe->nutrition)->toBe(['calories' => 200, 'protein' => 12, 'carbs' => 2, 'fat' => 14]);
    expect($recipe->ingredients()->wherePivot('ingredient_id', $telur->id)->first()->pivot->quantity)->toBe('2 butir');
    expect($recipe->ingredients()->wherePivot('ingredient_id', $garam->id)->first()->pivot->quantity)->toBe('1 sdt');
});

it('stores null instead of an all-null array when the nutrition fieldset is left blank', function () {
    $user = makeHouseholdUser('Joko');
    auth()->login($user);

    Livewire::test(CreateRecipe::class)
        ->fillForm([
            'name' => 'Resep Tanpa Gizi',
            'steps' => 'Aduk rata',
            'source' => Recipe::SOURCE_SEED,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recipe = Recipe::where('name', 'Resep Tanpa Gizi')->firstOrFail();
    expect($recipe->nutrition)->toBeNull();
});
