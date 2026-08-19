<?php

use App\Modules\Bills\Models\Bill;
use App\Modules\Bills\Models\BillPayment;
use App\Modules\Calendar\Models\Event;
use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\Tasks\Models\Task;

it('creates a household with default categories when a user registers', function () {
    $user = makeHouseholdUser('Andi');

    expect($user->current_household_id)->not->toBeNull();
    expect($user->households()->count())->toBe(1);
    expect($user->households()->first()->pivot->role)->toBe('owner');

    // Category is household-scoped: only visible while authenticated as a
    // member of that household, by design (see BelongsToHousehold).
    auth()->login($user);
    $household = $user->currentHousehold;
    expect($household->categories()->count())->toBeGreaterThan(0);
});

it('never leaks shopping lists across households', function () {
    $userA = makeHouseholdUser('Budi');
    $userB = makeHouseholdUser('Citra');

    auth()->login($userA);
    $listA = ShoppingList::create(['name' => 'Belanja Budi']);

    auth()->login($userB);
    $listB = ShoppingList::create(['name' => 'Belanja Citra']);

    auth()->login($userA);
    $visible = ShoppingList::all();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->id)->toBe($listA->id);

    auth()->login($userB);
    $visible = ShoppingList::all();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->id)->toBe($listB->id);
});

it('never leaks transactions or categories across households', function () {
    $userA = makeHouseholdUser('Dewi');
    $userB = makeHouseholdUser('Eka');

    auth()->login($userA);
    $categoryA = Category::first();
    Transaction::create([
        'category_id' => $categoryA->id,
        'user_id' => $userA->id,
        'type' => Transaction::TYPE_EXPENSE,
        'amount' => 50000,
        'occurred_on' => now(),
    ]);

    auth()->login($userB);
    expect(Category::count())->toBeGreaterThan(0);
    expect(Category::whereKey($categoryA->id)->exists())->toBeFalse();
    expect(Transaction::count())->toBe(0);

    auth()->login($userA);
    expect(Transaction::count())->toBe(1);
});

it('never leaks events or tasks across households', function () {
    $userA = makeHouseholdUser('Fajar');
    $userB = makeHouseholdUser('Gita');

    auth()->login($userA);
    $eventA = Event::create(['title' => 'Rapat RT', 'starts_at' => now()->addDay()]);
    $taskA = Task::create(['title' => 'Sapu halaman', 'priority' => 'tinggi']);

    auth()->login($userB);
    expect(Event::count())->toBe(0);
    expect(Task::count())->toBe(0);
    expect(Event::whereKey($eventA->id)->exists())->toBeFalse();
    expect(Task::whereKey($taskA->id)->exists())->toBeFalse();

    auth()->login($userA);
    expect(Event::count())->toBe(1);
    expect(Task::count())->toBe(1);
});

it('never leaks bills or their payments across households', function () {
    $userA = makeHouseholdUser('Hendra');
    $userB = makeHouseholdUser('Indah');

    auth()->login($userA);
    $billA = Bill::create([
        'name' => 'Listrik PLN',
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=20',
        'starts_on' => now()->startOfMonth()->addDays(19),
        'reminder_days_before' => 2,
    ]);
    $paymentA = BillPayment::create([
        'bill_id' => $billA->id,
        'period_on' => now()->startOfMonth()->addDays(19),
        'amount' => 432500,
        'user_id' => $userA->id,
        'paid_at' => now(),
    ]);

    auth()->login($userB);
    expect(Bill::count())->toBe(0);
    expect(BillPayment::count())->toBe(0);
    expect(Bill::whereKey($billA->id)->exists())->toBeFalse();
    expect(BillPayment::whereKey($paymentA->id)->exists())->toBeFalse();
    // Termasuk lewat penelusuran relasi, bukan cuma query langsung.
    expect(Bill::withoutGlobalScope('household')->find($billA->id)->payments()->count())->toBe(0);

    auth()->login($userA);
    expect(Bill::count())->toBe(1);
    expect(BillPayment::count())->toBe(1);
});
