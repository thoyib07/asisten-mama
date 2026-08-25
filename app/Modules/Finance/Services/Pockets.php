<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\BudgetAllocation;
use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Kondisi tiap kantong (kategori expense aktif) untuk satu periode.
 *
 * Dihitung dari data mentah tiap kali dipanggil — tidak ada kolom snapshot yang dipelihara
 * di write-path. Konsekuensinya cuma dua query agregat per render; imbalannya angka kantong
 * tidak mungkin drift dari transaksi aslinya.
 */
class Pockets
{
    /**
     * Periode yang boleh dipilih user: 12 periode terakhir menurut tanggal reset yang berlaku
     * sekarang, ditambah periode mana pun yang sudah punya alokasi.
     *
     * Tambahan itu penting: mengubah `budget_period_reset_day` menggeser irama periode, dan tanpa
     * ini alokasi yang tercatat dengan irama lama jadi tidak bisa dibuka lagi lewat UI mana pun.
     *
     * @return Collection<int, array{value: string, title: string, range: ?string}>
     */
    public static function selectablePeriods(int $resetDay): Collection
    {
        return collect(FinancePeriod::recent($resetDay))
            ->map(fn (CarbonImmutable $p) => $p->toDateString())
            ->merge(
                BudgetAllocation::distinct()
                    ->pluck('period_start')
                    ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            )
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn (string $value) => ['value' => $value] + FinancePeriod::label($value));
    }

    /**
     * `budget`/`remaining` bernilai null untuk kantong yang belum dianggarkan periode ini —
     * itu bukan error, transaksinya tetap boleh masuk (cuma tidak ada pembanding).
     *
     * @return Collection<int, array{category: Category, allocation: ?BudgetAllocation, budget: ?float, spent: float, remaining: ?float}>
     */
    public static function forPeriod(CarbonInterface|string $periodStart): Collection
    {
        $start = CarbonImmutable::parse($periodStart)->startOfDay();
        $end = FinancePeriod::endOf($start);

        $allocations = BudgetAllocation::withSum('topups', 'amount')
            ->where('period_start', $start->toDateString())
            ->get()
            ->keyBy('category_id');

        $spent = Transaction::expense()
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('category_id')
            ->selectRaw('category_id, sum(amount) as total')
            ->pluck('total', 'category_id');

        return Category::active()
            ->where('type', Category::TYPE_EXPENSE)
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) use ($allocations, $spent) {
                $allocation = $allocations->get($category->id);
                $budget = $allocation
                    ? (float) $allocation->amount + (float) $allocation->topups_sum_amount
                    : null;
                $used = (float) ($spent[$category->id] ?? 0);

                return [
                    'category' => $category,
                    'allocation' => $allocation,
                    'budget' => $budget,
                    'spent' => $used,
                    'remaining' => $budget === null ? null : $budget - $used,
                ];
            });
    }
}
