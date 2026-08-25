<?php

use App\Livewire\Beranda;
use App\Modules\Bills\Models\Bill;
use App\Modules\Bills\Services\RecordBillPayment;
use App\Modules\Calendar\Models\Event;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;
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

it('counts only bills that are due soon or overdue', function () {
    auth()->login(makeHouseholdUser('Hesti'));

    // Jatuh tempo kemarin, belum dibayar — harus dihitung.
    Bill::create([
        'name' => 'Telat',
        'starts_on' => now()->subDay()->toDateString(),
        'reminder_days_before' => 2,
    ]);

    // Jatuh tempo lusa — masih dalam jendela 7 hari.
    Bill::create([
        'name' => 'Sebentar lagi',
        'starts_on' => now()->addDays(2)->toDateString(),
        'reminder_days_before' => 2,
    ]);

    // Jatuh tempo dua bulan lagi — belum perlu muncul di lencana Beranda.
    Bill::create([
        'name' => 'Masih lama',
        'starts_on' => now()->addMonths(2)->toDateString(),
        'reminder_days_before' => 2,
    ]);

    Livewire::test(Beranda::class)->assertViewHas('dueBillCount', 2);
});

it('stops counting a bill once its due occurrence is paid', function () {
    $user = makeHouseholdUser('Ivan');
    auth()->login($user);

    $bill = Bill::create([
        'name' => 'Listrik',
        'starts_on' => now()->subDay()->toDateString(),
        'reminder_days_before' => 2,
    ]);

    Livewire::test(Beranda::class)->assertViewHas('dueBillCount', 1);

    app(RecordBillPayment::class)->record($bill, now()->subDay()->toDateString(), 400000, $user);

    Livewire::test(Beranda::class)->assertViewHas('dueBillCount', 0);
});

it('does not run one payment query per bill when rendering the badge', function () {
    auth()->login(makeHouseholdUser('Hesti'));

    foreach (range(1, 6) as $i) {
        Bill::create([
            'name' => "Tagihan {$i}",
            'starts_on' => now()->addDays($i)->toDateString(),
            'reminder_days_before' => 2,
        ]);
    }

    $paymentQueries = 0;
    DB::listen(function ($query) use (&$paymentQueries) {
        if (str_contains($query->sql, 'bill_payments')) {
            $paymentQueries++;
        }
    });

    Livewire::test(Beranda::class);

    // Satu eager-load, bukan satu query per tagihan. BillSchedule::paidPeriods() membuat query
    // builder baru dari relasinya, jadi eager-load saja tidak cukup — method itu harus membaca
    // relasi yang sudah dimuat. Kalau salah satu sisi hilang, angka ini balik jadi 6.
    expect($paymentQueries)->toBeLessThanOrEqual(1);
});
