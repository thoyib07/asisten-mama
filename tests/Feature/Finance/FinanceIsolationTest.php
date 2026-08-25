<?php

use App\Modules\Finance\Livewire\FinancePage;
use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;
use Livewire\Livewire;

it('refuses a category belonging to another household', function () {
    $rina = makeHouseholdUser('Rina');
    $budi = makeHouseholdUser('Budi');

    // Kategori default sudah di-seed oleh Household::createWithOwner untuk tiap keluarga.
    auth()->login($budi);
    $budiCategory = Category::where('type', 'expense')->first();
    expect($budiCategory)->not->toBeNull();

    auth()->login($rina);

    Livewire::test(FinancePage::class)
        ->set('categoryId', $budiCategory->id)
        ->set('amount', 50000)
        ->set('occurredOn', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['categoryId']);

    expect(Transaction::withoutGlobalScope('household')->count())->toBe(0);
});

it('accepts a category from the acting household', function () {
    $rina = makeHouseholdUser('Rina');
    auth()->login($rina);

    $own = Category::where('type', 'expense')->first();

    Livewire::test(FinancePage::class)
        ->set('categoryId', $own->id)
        ->set('amount', 50000)
        ->set('occurredOn', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(Transaction::count())->toBe(1);
    expect(Transaction::first()->category_id)->toBe($own->id);
});
