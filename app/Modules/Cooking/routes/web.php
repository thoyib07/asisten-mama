<?php

use App\Modules\Cooking\Livewire\FavoritesList;
use App\Modules\Cooking\Livewire\RecipeFinder;
use App\Modules\Cooking\Livewire\RecipeList;
use App\Modules\Cooking\Models\Recipe;
use Illuminate\Support\Facades\Route;

// "Tab" Buku Resep = dua route, bukan komponen pembungkus — RecipeList & RecipeFinder tetap
// utuh (tests/Feature/Cooking/RecipeFinderTest.php memanggil komponennya langsung).
// /resep/bahan harus dideklarasikan sebelum /resep/{recipe} supaya tidak tertelan wildcard.
Route::get('/resep', RecipeList::class)->name('recipes.index');
Route::get('/resep/bahan', RecipeFinder::class)->name('cooking.cari');
Route::get('/resep/{recipe}', function (Recipe $recipe) {
    return view('recipes.show', ['recipe' => $recipe->load('ingredients')]);
})->name('recipes.show');

// Tautan lama & entri PWA cache yang sudah beredar.
Route::permanentRedirect('/recipes', '/resep');
Route::permanentRedirect('/cari', '/resep/bahan');

Route::middleware('auth')->group(function () {
    Route::get('/favorites', FavoritesList::class)->name('favorites.index');
});
