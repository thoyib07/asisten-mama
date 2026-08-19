<?php

namespace App\Modules\Bills\Services;

use App\Models\User;
use App\Modules\Bills\Models\Bill;
use App\Modules\Bills\Models\BillPayment;
use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Menandai satu occurrence tagihan sebagai lunas, sekaligus mencatatnya sebagai pengeluaran
 * di Finance.
 *
 * Dependency searah Bills -> Finance, pola yang sama dengan MissingIngredientsToShoppingList:
 * Finance tidak perlu tahu apa pun soal modul Tagihan.
 */
class RecordBillPayment
{
    private const MONTHS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ags', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    public function record(Bill $bill, string $periodOn, float $amount, User $user): BillPayment
    {
        // periodOn berasal dari input user. Divalidasi sebelum menyentuh query apa pun —
        // string sembarang yang lolos ke `where period_on = ...` bukan cuma error, tapi
        // membatalkan seluruh transaksi DB yang sedang berjalan di Postgres.
        try {
            $period = Carbon::parse($periodOn)->startOfDay();
        } catch (Throwable) {
            throw new InvalidArgumentException("Tanggal periode tidak valid: {$periodOn}");
        }

        if ($bill->payments()->where('period_on', $period->toDateString())->exists()) {
            throw new RuntimeException('Periode tagihan ini sudah ditandai lunas.');
        }

        return DB::transaction(function () use ($bill, $period, $amount, $user) {

            $transaction = Transaction::create([
                'household_id' => $bill->household_id,
                'category_id' => $bill->category_id ?? $this->defaultCategoryId(),
                'user_id' => $user->id,
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => $amount,
                'description' => 'Tagihan: '.$bill->name
                    .' ('.self::MONTHS[$period->month].' '.$period->year.')',
                'occurred_on' => now()->toDateString(),
            ]);

            return BillPayment::create([
                'household_id' => $bill->household_id,
                'bill_id' => $bill->id,
                'period_on' => $period->toDateString(),
                'amount' => $amount,
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'paid_at' => now(),
            ]);
        });
    }

    /** Kategori "Tagihan" adalah salah satu dari 7 kategori yang di-seed tiap household dibuat. */
    private function defaultCategoryId(): ?int
    {
        return Category::where('type', Category::TYPE_EXPENSE)
            ->where('name', 'Tagihan')
            ->value('id');
    }
}
