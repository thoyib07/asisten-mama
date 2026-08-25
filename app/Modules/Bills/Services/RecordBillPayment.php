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
                'category_id' => $bill->category_id ?? $this->defaultCategoryId($bill),
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

    /**
     * Kategori "Tagihan" adalah salah satu dari 7 kategori yang di-seed tiap household dibuat,
     * tapi namanya boleh diedit household (docs/prd/finance.md §6.8) — jadi pencarian by-name
     * bisa meleset. Kalau meleset, jatuh ke kantong pengeluaran tertua, bukan null: sejak §6.6
     * pengeluaran tanpa kantong adalah kondisi yang tidak boleh ada, dan jalur ini tidak lewat
     * validasi FinancePage.
     */
    private function defaultCategoryId(Bill $bill): ?int
    {
        // withoutGlobalScope + filter eksplisit: scope household membaca Auth::user(), sementara
        // transaksinya ditulis atas nama $bill->household_id. Pemanggil di luar sesi web (atau
        // admin di guard lain) akan dapat query kosong -> null -> pengeluaran tanpa kantong.
        return Category::withoutGlobalScope('household')
            ->where('household_id', $bill->household_id)
            ->active()
            ->where('type', Category::TYPE_EXPENSE)
            ->orderByRaw("case when name = 'Tagihan' then 0 else 1 end")
            ->orderBy('id')
            ->value('id');
    }
}
