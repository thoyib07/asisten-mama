<?php

use App\Livewire\Beranda;
use App\Modules\Calendar\Models\Event;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\Tasks\Models\Task;
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

it('shows the nearest upcoming event and real task progress', function () {
    auth()->login(makeHouseholdUser('Hana'));

    Event::create(['title' => 'Acara jauh', 'starts_at' => now()->addWeek()]);
    Event::create(['title' => 'Acara terdekat', 'starts_at' => now()->addHour()]);
    // Sudah lewat: tidak boleh muncul sebagai "terdekat".
    Event::create(['title' => 'Acara kemarin', 'starts_at' => now()->subDay()]);

    Task::create(['title' => 'Belum beres']);
    Task::create(['title' => 'Sudah beres', 'is_done' => true]);

    Livewire::test(Beranda::class)
        ->assertViewHas('pendingTaskCount', 1)
        ->assertViewHas('taskProgress', 50)
        ->assertSee('Acara terdekat')
        ->assertDontSee('Acara jauh')
        ->assertDontSee('Acara kemarin');
});
