<?php

use App\Modules\Cooking\Livewire\FavoritesList;
use App\Modules\Cooking\Livewire\RecipeFinder;
use App\Modules\Cooking\Livewire\RecipeList;
use App\Modules\Cooking\Models\Recipe;
use Illuminate\Support\Facades\Route;

// "Tab" Buku Resep = dua route, bukan komponen pembungkus — RecipeList & RecipeFinder tetap
// utuh (tests/Feature/Cooking/RecipeFinderTest.php memanggil komponennya langsung).
//
// Katalog resep terbuka untuk tamu: ini "cicipan" produk untuk pemasaran, dan katalognya
// global/shared (bukan household-scoped), jadi tidak ada data keluarga yang bisa bocor.
// Yang TIDAK ikut dibuka, dan alasannya — ini temuan code review d8a4741, jangan dilonggarkan
// tanpa menyelesaikan sebabnya lebih dulu:
//   - /resep/bahan (RecipeFinder) punya tombol "Eksplor dengan AI" yang memanggil Groq dan
//     menulis ke katalog resep global. Pengunjung anonim melewati cap kuota per-household di
//     AiQuotaGuard karena current_household_id-nya null, jadi kuotanya bisa dihabiskan.
//   - /favorites butuh user_id. Tombol favorit & rating di dalam halaman resep sudah dibungkus
//     @auth di bladenya (recipe-list.blade.php, recipes/show.blade.php).
//
// URUTAN PENTING: /resep/bahan harus dideklarasikan sebelum /resep/{recipe}, kalau tidak
// wildcard-nya menelan duluan dan "bahan" diperlakukan sebagai id resep (404). Karena keduanya
// sekarang beda middleware, ini tidak lagi terjaga otomatis oleh satu grup — jangan pindahkan
// baris di bawah ini ke bawah wildcard-nya.
Route::get('/resep', RecipeList::class)->name('recipes.index');

Route::get('/resep/bahan', RecipeFinder::class)->middleware('auth')->name('cooking.cari');

Route::get('/resep/{recipe}', function (Recipe $recipe) {
    return view('recipes.show', ['recipe' => $recipe->load('ingredients')]);
})->name('recipes.show');

Route::get('/favorites', FavoritesList::class)->middleware('auth')->name('favorites.index');

// Tautan lama & entri PWA cache yang sudah beredar — sengaja di luar grup auth supaya
// redirect-nya tetap 301 dan tidak memantul lewat halaman login dulu.
Route::permanentRedirect('/recipes', '/resep');
Route::permanentRedirect('/cari', '/resep/bahan');
