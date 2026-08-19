<?php

namespace App\Modules\Bills\Services;

use App\Modules\Bills\Models\Bill;
use Carbon\Carbon;
use RRule\RRule;

/**
 * Menerjemahkan kolom `rrule` sebuah tagihan jadi daftar tanggal jatuh tempo.
 *
 * Pengulangan sengaja tidak disimpan sebagai baris occurrence di DB — aturannya diekspansi
 * saat dibutuhkan, dan di feed ICS diserahkan mentah ke Google. Konsekuensinya aplikasi ini
 * tidak butuh scheduler/cron sama sekali.
 */
class BillSchedule
{
    /** Sejauh mana `nextUnpaid()` mencari ke depan — tagihan tahunan butuh lebih dari setahun. */
    private const HORIZON_YEARS = 3;

    /**
     * Sejauh mana ke belakang tagihan yang telat masih dianggap "harus dibayar". Dipakai
     * bersama oleh daftar Tagihan, lencana Beranda, dan feed ICS — kalau ketiganya memakai
     * jendela berbeda, tagihan bisa terhitung di lencana tapi kartunya tidak menawarkan
     * tombol bayar, dan user melihat angka yang tidak bisa dihilangkan.
     */
    public const LOOKBACK_MONTHS = 12;

    public static function lookbackFrom(): string
    {
        return now()->subMonths(self::LOOKBACK_MONTHS)->toDateString();
    }

    /** Apakah tanggal ini benar-benar salah satu jatuh tempo tagihan tsb. */
    public function isOccurrence(Bill $bill, string $date): bool
    {
        return in_array($date, $this->occurrencesBetween($bill, $date, $date), true);
    }

    /** @return array<int, string> tanggal Y-m-d, urut menaik */
    public function occurrencesBetween(Bill $bill, string $from, string $to): array
    {
        $start = $bill->starts_on->copy()->startOfDay();
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();

        if (! $bill->rrule) {
            return $start->betweenIncluded($from, $to) ? [$start->toDateString()] : [];
        }

        $occurrences = (new RRule($bill->rrule, $start))->getOccurrencesBetween($from, $to);

        return array_map(fn ($date) => $date->format('Y-m-d'), $occurrences);
    }

    /** @return array<int, string> */
    public function unpaidOccurrencesBetween(Bill $bill, string $from, string $to): array
    {
        return array_values(array_diff(
            $this->occurrencesBetween($bill, $from, $to),
            $this->paidPeriods($bill)
        ));
    }

    public function nextUnpaid(Bill $bill, ?string $from = null): ?string
    {
        $from ??= now()->toDateString();
        $horizon = Carbon::parse($from)->addYears(self::HORIZON_YEARS)->toDateString();

        return $this->unpaidOccurrencesBetween($bill, $from, $horizon)[0] ?? null;
    }

    /** @return array<int, string> */
    public function paidPeriods(Bill $bill): array
    {
        // Feed ICS dilayani lewat token tanpa sesi. Global scope BelongsToHousehold memfilter
        // `household_id = null` kalau tidak ada user login, yang membuat daftar lunas selalu
        // kosong dan tagihan yang sudah dibayar muncul lagi di kalender. Filter `bill_id` di
        // bawah sudah merupakan scope yang lengkap — tagihannya sendiri diambil ter-scope.
        return $bill->payments()
            ->withoutGlobalScope('household')
            ->pluck('period_on')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();
    }
}
