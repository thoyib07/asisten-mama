<?php

use App\Modules\Bills\Models\Bill;
use App\Modules\Bills\Models\BillPayment;
use App\Modules\Bills\Services\RecordBillPayment;
use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;

beforeEach(function () {
    $this->owner = makeHouseholdUser('Budi');
    auth()->login($this->owner);
    $this->service = app(RecordBillPayment::class);
});

function payableBill(array $attributes = []): Bill
{
    return Bill::create(array_merge([
        'name' => 'Listrik PLN',
        'amount_estimate' => 450000,
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=20',
        'starts_on' => '2026-01-20',
        'reminder_days_before' => 2,
    ], $attributes));
}

it('records the payment and books an expense in Finance', function () {
    $bill = payableBill();

    $payment = $this->service->record($bill, '2026-04-20', 432500, $this->owner);

    expect(BillPayment::count())->toBe(1)
        ->and((float) $payment->amount)->toBe(432500.0)
        ->and($payment->household_id)->toBe($this->owner->current_household_id);

    $transaction = Transaction::first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->type)->toBe(Transaction::TYPE_EXPENSE)
        // Nominal yang masuk Finance adalah nominal ASLI, bukan perkiraan di tagihan.
        ->and((float) $transaction->amount)->toBe(432500.0)
        ->and($transaction->user_id)->toBe($this->owner->id)
        ->and($payment->transaction_id)->toBe($transaction->id);
});

it('books the expense against the household "Tagihan" category by default', function () {
    $payment = $this->service->record(payableBill(), '2026-04-20', 432500, $this->owner);

    $tagihan = Category::where('type', Category::TYPE_EXPENSE)->where('name', 'Tagihan')->first();

    expect($payment->transaction->category_id)->toBe($tagihan->id);
});

it('books the expense against the category chosen on the bill', function () {
    $pendidikan = Category::where('name', 'Pendidikan')->first();
    $bill = payableBill(['name' => 'SPP Sekolah', 'category_id' => $pendidikan->id]);

    $payment = $this->service->record($bill, '2026-04-20', 750000, $this->owner);

    expect($payment->transaction->category_id)->toBe($pendidikan->id);
});

it('names the bill and its period in the transaction description', function () {
    $payment = $this->service->record(payableBill(), '2026-04-20', 432500, $this->owner);

    expect($payment->transaction->description)->toContain('Listrik PLN')
        ->and($payment->transaction->description)->toContain('Apr 2026');
});

it('refuses to pay the same period twice', function () {
    $bill = payableBill();
    $this->service->record($bill, '2026-04-20', 432500, $this->owner);

    expect(fn () => $this->service->record($bill, '2026-04-20', 100000, $this->owner))
        ->toThrow(RuntimeException::class);

    // Percobaan kedua tidak boleh meninggalkan transaksi yatim di Finance.
    expect(BillPayment::count())->toBe(1)
        ->and(Transaction::count())->toBe(1);
});

it('rejects a period that is not a valid date before touching the database', function () {
    $bill = payableBill();

    // Tanggal periode datang dari input user. Kalau string sembarang lolos sampai ke
    // `where period_on = ...`, Postgres membatalkan seluruh transaksi yang sedang berjalan —
    // bukan cuma menolak satu query.
    expect(fn () => $this->service->record($bill, 'bukan-tanggal', 432500, $this->owner))
        ->toThrow(InvalidArgumentException::class);

    expect(BillPayment::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
});
