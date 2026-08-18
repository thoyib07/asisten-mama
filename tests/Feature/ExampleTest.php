<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Tautan lama /cari & /recipes dipertahankan sebagai redirect saat Buku Resep pindah ke
     * /resep + /resep/bahan (docs/ui-design.md §5.4) — bookmark & tautan yang sudah beredar
     * tidak boleh mati. (public/sw.js sendiri hanya precache "/" dan /manifest.json, jadi
     * service worker bukan alasannya.)
     */
    public function test_old_cooking_urls_still_reach_the_recipe_book(): void
    {
        $this->get('/cari')->assertRedirect('/resep/bahan');
        $this->get('/recipes')->assertRedirect('/resep');

        $this->get('/resep/bahan')->assertStatus(200);
        $this->get('/resep')->assertStatus(200);
    }
}
