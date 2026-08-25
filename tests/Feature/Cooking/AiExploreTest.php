<?php

use App\Modules\Cooking\Livewire\RecipeFinder;
use App\Modules\Cooking\Services\AiQuotaGuard;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('services.groq.endpoint', 'https://api.groq.test/v1/chat/completions');
    config()->set('services.groq.key', 'test-key');
    config()->set('services.groq.model', 'test-model');
});

it('requires authentication to reach the recipe pages', function () {
    $this->get('/resep')->assertRedirect('/login');
    $this->get('/resep/bahan')->assertRedirect('/login');
});

it('keeps the legacy redirects public so circulating links still resolve', function () {
    $this->get('/recipes')->assertRedirect('/resep');
    $this->get('/cari')->assertRedirect('/resep/bahan');
});

it('makes no Groq call at all once the quota is exhausted', function () {
    Http::fake();
    $user = makeHouseholdUser('Rina');
    auth()->login($user);

    // Habiskan cap household (7/hari) sebelum komponen dipanggil.
    $guard = new AiQuotaGuard;
    for ($i = 0; $i < 7; $i++) {
        $guard->check($user->current_household_id);
    }

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['bahan-yang-belum-pernah-ada'])
        ->call('exploreWithAi')
        ->assertSet('aiError', 'Kuota AI household kamu hari ini sudah habis.');

    // Termasuk panggilan IngredientValidator — dulu loop validasi jalan lebih dulu,
    // jadi bahan tak dikenal tetap menghabiskan panggilan Groq walau kuota sudah habis.
    Http::assertNothingSent();
});

it('surfaces a friendly message when Groq rate limits', function () {
    Http::fake(fn () => Http::response(['error' => 'slow down'], 429));
    auth()->login(makeHouseholdUser('Rina'));

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['telur'])
        ->call('exploreWithAi')
        ->assertSet('aiError', 'AI sedang sibuk, tunggu sebentar lalu coba lagi.');
});

it('surfaces a friendly message when Groq returns an unusable body', function () {
    Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => 'bukan json sama sekali']]]], 200));
    auth()->login(makeHouseholdUser('Rina'));

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['telur'])
        ->call('exploreWithAi')
        ->assertSet('aiError', 'Gagal mengambil resep AI. Coba lagi nanti.');
});
