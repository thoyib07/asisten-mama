<?php

namespace App\Modules\Finance\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Satu-satunya tempat tanggal periode kantong dihitung.
 *
 * Periode tidak selalu bulan kalender: household bisa mengatur `budget_period_reset_day`
 * (mis. ikut tanggal gajian), jadi periode berjalan bisa 25 Jul–24 Agt. Karena reset day
 * dibatasi 1–28, `addMonth()` di sini tidak pernah overflow — itulah alasan batas 28.
 */
class FinancePeriod
{
    public const MIN_RESET_DAY = 1;

    public const MAX_RESET_DAY = 28;

    /** Tanggal mulai periode yang memuat $date. */
    public static function startFor(int $resetDay, CarbonInterface|string $date = 'today'): CarbonImmutable
    {
        $resetDay = max(self::MIN_RESET_DAY, min(self::MAX_RESET_DAY, $resetDay));
        $date = CarbonImmutable::parse($date)->startOfDay();

        return $date->day >= $resetDay
            ? $date->setDay($resetDay)
            : $date->startOfMonth()->subMonth()->setDay($resetDay);
    }

    /** Tanggal terakhir periode yang mulai di $start (inklusif). */
    public static function endOf(CarbonInterface|string $start): CarbonImmutable
    {
        return CarbonImmutable::parse($start)->startOfDay()->addMonth()->subDay();
    }

    /**
     * Label hybrid: nama bulan besar untuk sekilas-lihat, rentang tanggal eksak sebagai
     * keterangan kecil. Bulan yang dipakai = bulan tempat mayoritas hari periode jatuh;
     * kalau seri (mis. reset day 15 di Februari) dipilih bulan akhir, karena siklus gajian
     * lebih dikenali dengan bulan tempat ia berakhir.
     *
     * `range` null kalau periodenya persis satu bulan kalender — rentangnya cuma mengulang
     * judul.
     *
     * @return array{title: string, range: ?string}
     */
    public static function label(CarbonInterface|string $start): array
    {
        $start = CarbonImmutable::parse($start)->startOfDay();
        $end = self::endOf($start);

        $daysInStartMonth = $start->daysInMonth - $start->day + 1;
        $anchor = $daysInStartMonth > $end->day ? $start : $end;

        return [
            'title' => $anchor->locale('id')->isoFormat('MMMM Y'),
            'range' => $start->day === 1
                ? null
                : $start->locale('id')->isoFormat('D MMM').' – '.$end->locale('id')->isoFormat('D MMM Y'),
        ];
    }

    /**
     * Periode berjalan + beberapa periode sebelumnya, terbaru duluan — isi dropdown filter.
     *
     * @return array<int, CarbonImmutable>
     */
    public static function recent(int $resetDay, int $count = 12, CarbonInterface|string $date = 'today'): array
    {
        $current = self::startFor($resetDay, $date);

        return array_map(fn (int $i) => $current->subMonths($i), range(0, $count - 1));
    }
}
