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
     *
     * Redirect-nya sengaja di luar grup auth supaya tetap 301 langsung ke tujuan, bukan
     * memantul lewat /login dulu. Tujuannya sendiri butuh auth — dulu tidak, dan itu membuat
     * pengunjung anonim bisa menghabiskan kuota Groq lewat tombol Eksplor AI.
     * Cakupan auth-nya ada di tests/Feature/Cooking/AiExploreTest.php.
     */
    public function test_old_cooking_urls_still_redirect_to_the_recipe_book(): void
    {
        $this->get('/cari')->assertRedirect('/resep/bahan');
        $this->get('/recipes')->assertRedirect('/resep');
    }
}
