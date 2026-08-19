<?php

use App\Modules\Bills\Livewire\BillList;
use App\Modules\Bills\Models\Bill;
use App\Modules\Bills\Models\BillPayment;
use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = makeHouseholdUser('Budi');
    auth()->login($this->owner);
});

it('turns the monthly preset into an rrule anchored on the due date', function () {
    Livewire::test(BillList::class)
        ->set('name', 'Listrik PLN')
        ->set('startsOn', '2026-09-20')
        ->set('repeat', 'bulanan')
        ->call('save')
        ->assertHasNoErrors();

    expect(Bill::first()->rrule)->toBe('FREQ=MONTHLY;BYMONTHDAY=20');
});

it('turns the yearly preset into an rrule anchored on month and day', function () {
    Livewire::test(BillList::class)
        ->set('name', 'PBB')
        ->set('startsOn', '2026-09-20')
        ->set('repeat', 'tahunan')
        ->call('save')
        ->assertHasNoErrors();

    expect(Bill::first()->rrule)->toBe('FREQ=YEARLY;BYMONTH=9;BYMONTHDAY=20');
});

it('stores no rrule for a one-off bill', function () {
    Livewire::test(BillList::class)
        ->set('name', 'Servis AC')
        ->set('startsOn', '2026-09-20')
        ->set('repeat', 'sekali')
        ->call('save')
        ->assertHasNoErrors();

    expect(Bill::first()->rrule)->toBeNull();
});

it('recognises its own presets when reopening a bill for editing', function () {
    Livewire::test(BillList::class)
        ->set('name', 'PBB')
        ->set('startsOn', '2026-09-20')
        ->set('repeat', 'tahunan')
        ->call('save');

    // Preset tidak disimpan di DB — cuma rrule-nya. Form harus bisa mengenalinya kembali,
    // bukan jatuh ke "Lanjutan" untuk tagihan yang dibuat lewat preset.
    Livewire::test(BillList::class)
        ->call('edit', Bill::first()->id)
        ->assertSet('repeat', 'tahunan')
        ->assertSet('customRrule', '');
});

it('shows a hand-written rrule as the advanced option when editing', function () {
    $bill = Bill::create([
        'name' => 'Iuran RT',
        'rrule' => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO',
        'starts_on' => '2026-09-21',
        'reminder_days_before' => 1,
    ]);

    Livewire::test(BillList::class)
        ->call('edit', $bill->id)
        ->assertSet('repeat', 'lanjutan')
        ->assertSet('customRrule', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO');
});

it('rejects a malformed hand-written rrule', function () {
    Livewire::test(BillList::class)
        ->set('name', 'Ngawur')
        ->set('startsOn', '2026-09-20')
        ->set('repeat', 'lanjutan')
        ->set('customRrule', 'INI BUKAN RRULE')
        ->call('save')
        ->assertHasErrors('customRrule');

    expect(Bill::count())->toBe(0);
});

it('rejects an rrule that never yields a due date', function () {
    Livewire::test(BillList::class)
        ->set('name', 'Tidak pernah jatuh tempo')
        ->set('startsOn', '2026-09-20')
        ->set('repeat', 'lanjutan')
        // 30 Februari tidak pernah ada.
        ->set('customRrule', 'FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=30')
        ->call('save')
        ->assertHasErrors('customRrule');

    expect(Bill::count())->toBe(0);
});

it('accepts a valid hand-written rrule', function () {
    Livewire::test(BillList::class)
        ->set('name', 'Iuran dua mingguan')
        ->set('startsOn', '2026-09-21')
        ->set('repeat', 'lanjutan')
        ->set('customRrule', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO')
        ->call('save')
        ->assertHasNoErrors();

    expect(Bill::first()->rrule)->toBe('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO');
});

it('books an expense in Finance when a due bill is marked paid', function () {
    $bill = Bill::create([
        'name' => 'Listrik PLN',
        'amount_estimate' => 450000,
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=20',
        'starts_on' => now()->subMonth()->startOfMonth()->addDays(19)->toDateString(),
        'reminder_days_before' => 2,
    ]);

    $component = Livewire::test(BillList::class);
    $due = $component->viewData('due')[$bill->id];

    $component->call('startPaying', $bill->id, $due)
        // Perkiraan cuma nilai awal; user mengganti dengan nominal asli.
        ->assertSet('payAmount', '450000')
        ->set('payAmount', '432500')
        ->call('confirmPaying')
        ->assertHasNoErrors();

    $transaction = Transaction::first();

    expect($transaction->type)->toBe(Transaction::TYPE_EXPENSE)
        ->and((float) $transaction->amount)->toBe(432500.0)
        ->and($transaction->category_id)->toBe(
            Category::where('name', 'Tagihan')->value('id')
        );
});

it('moves to the next occurrence once the current one is paid', function () {
    $bill = Bill::create([
        'name' => 'Listrik PLN',
        'amount_estimate' => 450000,
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=20',
        'starts_on' => now()->subMonths(2)->startOfMonth()->addDays(19)->toDateString(),
        'reminder_days_before' => 2,
    ]);

    $component = Livewire::test(BillList::class);
    $first = $component->viewData('due')[$bill->id];

    $component->call('startPaying', $bill->id, $first)
        ->set('payAmount', '400000')
        ->call('confirmPaying');

    expect(Livewire::test(BillList::class)->viewData('due')[$bill->id])
        ->not->toBe($first);
});

it('hides an archived bill from the list', function () {
    $bill = Bill::create([
        'name' => 'Internet lama',
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=5',
        'starts_on' => '2026-01-05',
        'reminder_days_before' => 2,
    ]);

    Livewire::test(BillList::class)
        ->assertSee('Internet lama')
        ->call('archive', $bill->id)
        ->assertDontSee('Internet lama');

    // Diarsipkan, bukan dihapus — riwayatnya harus tetap ada.
    expect(Bill::withoutGlobalScope('household')->count())->toBe(1);
});

it('changes the feed url when the token is regenerated', function () {
    $component = Livewire::test(BillList::class);
    $before = $component->viewData('feedUrl');

    $component->call('regenerateCalendarUrl');

    expect(Livewire::test(BillList::class)->viewData('feedUrl'))->not->toBe($before);
});

it('refuses to book a payment for a date that is not an occurrence', function () {
    $bill = Bill::create([
        'name' => 'Listrik PLN',
        'amount_estimate' => 450000,
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=20',
        'starts_on' => now()->subMonth()->startOfMonth()->addDays(19)->toDateString(),
        'reminder_days_before' => 2,
    ]);

    // payingPeriod adalah properti Livewire publik — klien bisa mengisinya sesuka hati,
    // nilai dari tombol di blade cuma usulan.
    Livewire::test(BillList::class)
        ->call('startPaying', $bill->id, now()->subMonth()->startOfMonth()->addDays(19)->toDateString())
        ->set('payingPeriod', now()->addYears(2)->startOfMonth()->addDays(6)->toDateString())
        ->set('payAmount', '400000')
        ->call('confirmPaying')
        ->assertHasErrors('payAmount');

    expect(BillPayment::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

it('still offers to settle a one-off bill that is long overdue', function () {
    // Lencana Beranda dan kartu Tagihan harus memakai jendela pencarian yang sama. Kalau
    // berbeda, tagihan sekali-jalan yang telat berbulan-bulan terhitung di lencana tapi
    // kartunya tidak menampilkan tombol "Tandai lunas" — user melihat angka yang tidak bisa
    // dihilangkan.
    $bill = Bill::create([
        'name' => 'Servis AC',
        'amount_estimate' => 300000,
        'rrule' => null,
        'starts_on' => now()->subMonths(6)->toDateString(),
        'reminder_days_before' => 2,
    ]);

    expect(Livewire::test(BillList::class)->viewData('due')[$bill->id])
        ->toBe($bill->starts_on->toDateString());
});

it('shows the calendar feed url when the connect panel is open', function () {
    $component = Livewire::test(BillList::class);

    $component->set('showConnect', true)
        ->assertSee($component->viewData('feedUrl'), escape: false)
        ->assertSee('Kalender lain');
});
