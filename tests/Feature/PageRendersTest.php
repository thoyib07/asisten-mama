<?php

use App\Modules\Cooking\Models\Recipe;

// Redesign 2026-08-18 menyentuh hampir semua blade sekaligus. Satu smoke test supaya halaman
// yang kelupaan (variabel hilang, komponen salah nama) ketahuan di suite, bukan di browser.

it('renders every customer-facing page for a household member', function (string $path) {
    auth()->login(makeHouseholdUser('Rani'));

    $this->get($path)->assertOk();
})->with([
    '/',
    '/kalender',
    '/tugas',
    '/shopping-list',
    '/resep',
    '/resep/bahan',
    '/favorites',
    '/keluarga',
    '/finance',
    '/finance/kantong',
    '/tagihan',
]);

// Batas tamu vs anggota keluarga. Separuh kedua adalah penjaga regresi untuk temuan code review
// d8a4741: /resep/bahan pernah terbuka tanpa auth dan pengunjung anonim bisa menghabiskan kuota
// Groq lewat "Eksplor dengan AI" (cap per-household di AiQuotaGuard terlewat karena
// current_household_id-nya null). Kalau test ini merah, lubang itu terbuka lagi.
it('lets a guest open the public pages', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/',
    '/resep',
]);

it('lets a guest open a recipe detail page', function () {
    $recipe = Recipe::create([
        'name' => 'Nasi Goreng Kampung',
        'steps' => ['Langkah 1'],
        'source' => Recipe::SOURCE_SEED,
    ]);

    $this->get("/resep/{$recipe->id}")->assertOk()->assertSee('Nasi Goreng Kampung');
});

it('keeps the household pages behind login for a guest', function (string $path) {
    $this->get($path)->assertRedirect();
})->with([
    '/resep/bahan',
    '/favorites',
    '/finance',
    '/finance/kantong',
    '/shopping-list',
    '/kalender',
    '/tugas',
    '/tagihan',
    '/keluarga',
    '/akun',
]);
