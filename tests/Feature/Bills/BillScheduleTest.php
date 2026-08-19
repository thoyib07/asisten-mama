<?php

use App\Modules\Bills\Models\Bill;
use App\Modules\Bills\Models\BillPayment;
use App\Modules\Bills\Services\BillSchedule;

beforeEach(function () {
    $this->owner = makeHouseholdUser('Budi');
    auth()->login($this->owner);
    $this->schedule = new BillSchedule;
});

function makeBill(array $attributes = []): Bill
{
    return Bill::create(array_merge([
        'name' => 'Listrik PLN',
        'amount_estimate' => 450000,
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=20',
        'starts_on' => '2026-01-20',
        'reminder_days_before' => 2,
    ], $attributes));
}

it('expands a monthly rrule into one occurrence per month', function () {
    $occurrences = $this->schedule->occurrencesBetween(
        makeBill(), '2026-03-01', '2026-05-31'
    );

    expect($occurrences)->toEqual(['2026-03-20', '2026-04-20', '2026-05-20']);
});

it('treats a bill without an rrule as a single occurrence on its start date', function () {
    $bill = makeBill(['rrule' => null, 'starts_on' => '2026-04-11']);

    expect($this->schedule->occurrencesBetween($bill, '2026-01-01', '2026-12-31'))
        ->toEqual(['2026-04-11']);
});

it('never returns occurrences before the bill starts', function () {
    $bill = makeBill(['starts_on' => '2026-04-20']);

    expect($this->schedule->occurrencesBetween($bill, '2026-01-01', '2026-05-31'))
        ->toEqual(['2026-04-20', '2026-05-20']);
});

it('skips periods that have already been paid', function () {
    $bill = makeBill();

    BillPayment::create([
        'bill_id' => $bill->id,
        'period_on' => '2026-04-20',
        'amount' => 430000,
        'user_id' => $this->owner->id,
        'paid_at' => now(),
    ]);

    expect($this->schedule->unpaidOccurrencesBetween($bill, '2026-03-01', '2026-05-31'))
        ->toEqual(['2026-03-20', '2026-05-20']);
});

it('finds the next unpaid occurrence on or after a given date', function () {
    $bill = makeBill();

    BillPayment::create([
        'bill_id' => $bill->id,
        'period_on' => '2026-04-20',
        'amount' => 430000,
        'user_id' => $this->owner->id,
        'paid_at' => now(),
    ]);

    // 20 April sudah lunas, jadi yang berikutnya Mei — bukan April.
    expect($this->schedule->nextUnpaid($bill, '2026-04-01'))->toBe('2026-05-20');
});

it('returns null when a one-off bill has already been paid', function () {
    $bill = makeBill(['rrule' => null, 'starts_on' => '2026-04-11']);

    BillPayment::create([
        'bill_id' => $bill->id,
        'period_on' => '2026-04-11',
        'amount' => 100000,
        'user_id' => $this->owner->id,
        'paid_at' => now(),
    ]);

    expect($this->schedule->nextUnpaid($bill, '2026-01-01'))->toBeNull();
});

it('handles a monthly rrule on day 31 without spilling into the next month', function () {
    // BYMONTHDAY=31 tidak ada di Februari/April — RFC 5545 melewatinya, tidak meluber ke tanggal 1.
    $bill = makeBill(['rrule' => 'FREQ=MONTHLY;BYMONTHDAY=31', 'starts_on' => '2026-01-31']);

    expect($this->schedule->occurrencesBetween($bill, '2026-01-01', '2026-04-30'))
        ->toEqual(['2026-01-31', '2026-03-31']);
});

it('expands a yearly rrule', function () {
    $bill = makeBill(['rrule' => 'FREQ=YEARLY;BYMONTH=8;BYMONTHDAY=31', 'starts_on' => '2026-08-31']);

    expect($this->schedule->occurrencesBetween($bill, '2026-01-01', '2028-12-31'))
        ->toEqual(['2026-08-31', '2027-08-31', '2028-08-31']);
});
