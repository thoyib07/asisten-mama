<?php

use App\Modules\Cooking\Livewire\FavoritesList;
use App\Modules\Cooking\Livewire\RecipeFinder;
use App\Modules\Cooking\Livewire\RecipeList;
use App\Modules\Cooking\Models\Recipe;
use Illuminate\Support\Facades\Route;

// "Tab" Buku Resep = dua route, bukan komponen pembungkus — RecipeList & RecipeFinder tetap
// utuh (tests/Feature/Cooking/RecipeFinderTest.php memanggil komponennya langsung).
// /resep/bahan harus dideklarasikan sebelum /resep/{recipe} supaya tidak tertelan wildcard.
// Semua halaman resep butuh auth, sama seperti route customer lainnya. RecipeFinder memuat
// tombol "Eksplor dengan AI" yang memanggil Groq dan menulis ke katalog resep global — tanpa
// auth, pengunjung anonim bisa menghabiskan kuota (AiQuotaGuard melewatkan cap per-household
// karena current_household_id-nya null) dan menulis ke katalog bersama.
Route::middleware('auth')->group(function () {
    Route::get('/resep', RecipeList::class)->name('recipes.index');
    Route::get('/resep/bahan', RecipeFinder::class)->name('cooking.cari');
    Route::get('/resep/{recipe}', function (Recipe $recipe) {
        return view('recipes.show', ['recipe' => $recipe->load('ingredients')]);
    })->name('recipes.show');

    Route::get('/favorites', FavoritesList::class)->name('favorites.index');
});

// Tautan lama & entri PWA cache yang sudah beredar — sengaja di luar grup auth supaya
// redirect-nya tetap 301 dan tidak memantul lewat halaman login dulu.
Route::permanentRedirect('/recipes', '/resep');
Route::permanentRedirect('/cari', '/resep/bahan');
