<?php

use App\Livewire\Beranda;
use App\Modules\ShoppingList\Models\ShoppingList;
use Livewire\Livewire;

it('redirects guests away from the beranda route', function () {
    $this->get('/')->assertRedirect();
});

it('only counts pending shopping items from the current household', function () {
    $userA = makeHouseholdUser('Fani');
    $userB = makeHouseholdUser('Gita');

    auth()->login($userA);
    $listA = ShoppingList::create(['name' => 'Belanja Fani']);
    $listA->items()->create(['name' => 'Telur', 'is_checked' => false]);
    $listA->items()->create(['name' => 'Gula', 'is_checked' => true]);

    auth()->login($userB);
    $listB = ShoppingList::create(['name' => 'Belanja Gita']);
    $listB->items()->create(['name' => 'Beras', 'is_checked' => false]);
    $listB->items()->create(['name' => 'Minyak', 'is_checked' => false]);

    auth()->login($userA);
    Livewire::test(Beranda::class)
        ->assertViewHas('pendingShoppingCount', 1);
});
