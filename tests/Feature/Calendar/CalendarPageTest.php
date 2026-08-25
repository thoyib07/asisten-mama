<?php

use App\Modules\Calendar\Livewire\CalendarPage;
use App\Modules\Calendar\Models\Event;
use Carbon\CarbonInterface;
use Livewire\Livewire;

it('adds an event and jumps to its date', function () {
    $owner = makeHouseholdUser('Budi');
    auth()->login($owner);

    $target = now()->addMonth()->startOfMonth()->addDays(9)->setTime(14, 0);

    Livewire::test(CalendarPage::class)
        ->set('title', 'Rapat sekolah')
        ->set('userId', $owner->id)
        ->set('startsAt', $target->format('Y-m-d\TH:i'))
        ->set('endsAt', $target->copy()->addHours(2)->format('Y-m-d\TH:i'))
        ->call('addEvent')
        ->assertHasNoErrors()
        // Kalender ikut pindah ke bulan & tanggal acara baru, bukan bertahan di bulan berjalan.
        ->assertSet('month', $target->format('Y-m'))
        ->assertSet('selected', $target->toDateString())
        ->assertSee('Rapat sekolah');

    expect(Event::first()->household_id)->toBe($owner->current_household_id);
});

it('rejects an end time before the start', function () {
    auth()->login(makeHouseholdUser('Budi'));

    Livewire::test(CalendarPage::class)
        ->set('title', 'Terbalik')
        ->set('startsAt', now()->setTime(10, 0)->format('Y-m-d\TH:i'))
        ->set('endsAt', now()->setTime(9, 0)->format('Y-m-d\TH:i'))
        ->call('addEvent')
        ->assertHasErrors('endsAt');

    expect(Event::count())->toBe(0);
});

it('shows only the selected day agenda', function () {
    auth()->login(makeHouseholdUser('Budi'));

    Event::create(['title' => 'Acara hari ini', 'starts_at' => today()->setTime(9, 0)]);
    Event::create(['title' => 'Acara besok', 'starts_at' => today()->addDay()->setTime(9, 0)]);

    Livewire::test(CalendarPage::class)
        ->assertSee('Acara hari ini')
        ->assertDontSee('Acara besok')
        ->call('selectDay', today()->addDay()->toDateString())
        ->assertSee('Acara besok')
        ->assertDontSee('Acara hari ini');
});

it('walks between months', function () {
    auth()->login(makeHouseholdUser('Budi'));

    Livewire::test(CalendarPage::class)
        ->call('shiftMonth', 1)
        ->assertSet('month', now()->addMonth()->format('Y-m'))
        ->call('shiftMonth', -2)
        ->assertSet('month', now()->subMonth()->format('Y-m'));
});

it('does not overflow short months when walked from the 31st', function () {
    $this->travelTo('2026-01-31');
    auth()->login(makeHouseholdUser('Budi'));

    Livewire::test(CalendarPage::class)
        ->call('shiftMonth', 1)
        ->assertSet('month', '2026-02')
        ->call('shiftMonth', 1)
        ->assertSet('month', '2026-03');
});

it('renders a whole number of week rows, with the first cell matching the first header', function () {
    auth()->login(makeHouseholdUser('Budi'));

    // Dua invarian yang dulu sama-sama salah: grid dimulai hari Minggu sementara header-nya
    // Senin-dulu, dan start/end sama-sama Minggu sehingga jumlah sel selalu 1 (mod 7).
    foreach (['2026-01', '2026-02', '2026-08', '2027-02'] as $month) {
        $view = Livewire::test(CalendarPage::class)->set('month', $month)->viewData('gridStart');
        $end = Livewire::test(CalendarPage::class)->set('month', $month)->viewData('gridEnd');

        $cells = $view->diffInDays($end) + 1;

        expect($cells % 7)->toBe(0, "bulan {$month} menghasilkan {$cells} sel, bukan kelipatan 7");
        expect($view->dayOfWeek)->toBe(CarbonInterface::MONDAY, "bulan {$month} tidak mulai hari Senin");
    }
});
