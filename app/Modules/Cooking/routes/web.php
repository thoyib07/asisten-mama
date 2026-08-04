<?php

use App\Modules\Cooking\Livewire\FavoritesList;
use App\Modules\Cooking\Livewire\RecipeFinder;
use App\Modules\Cooking\Livewire\RecipeList;
use App\Modules\Cooking\Models\Recipe;
use Illuminate\Support\Facades\Route;

Route::get('/cari', RecipeFinder::class)->name('cooking.cari');
Route::get('/recipes', RecipeList::class)->name('recipes.index');
Route::get('/recipes/{recipe}', function (Recipe $recipe) {
    return view('recipes.show', ['recipe' => $recipe->load('ingredients')]);
})->name('recipes.show');

Route::middleware('auth')->group(function () {
    Route::get('/favorites', FavoritesList::class)->name('favorites.index');
});
