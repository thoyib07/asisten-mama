<?php

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
    '/tagihan',
]);
