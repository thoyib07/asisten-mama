<?php

use App\Modules\Finance\Services\FinancePeriod;

it('memakai bulan kalender saat reset day tanggal 1', function () {
    $start = FinancePeriod::startFor(1, '2026-08-15');

    expect($start->toDateString())->toBe('2026-08-01');
    expect(FinancePeriod::endOf($start)->toDateString())->toBe('2026-08-31');
    expect(FinancePeriod::label($start))->toBe(['title' => 'Agustus 2026', 'range' => null]);
});

it('mulai periode di hari reset itu sendiri, bukan periode sebelumnya', function () {
    expect(FinancePeriod::startFor(25, '2026-08-25')->toDateString())->toBe('2026-08-25');
});

it('jatuh ke bulan sebelumnya kalau hari ini belum sampai tanggal reset', function () {
    $start = FinancePeriod::startFor(25, '2026-08-24');

    expect($start->toDateString())->toBe('2026-07-25');
    expect(FinancePeriod::endOf($start)->toDateString())->toBe('2026-08-24');
    expect(FinancePeriod::label($start))->toBe([
        'title' => 'Agustus 2026',
        'range' => '25 Jul – 24 Agt 2026',
    ]);
});

it('tidak overflow saat bulan sebelumnya lebih pendek', function () {
    // startOfMonth()->subMonth() dulu: 10 Mar → 1 Mar → 1 Feb → 28 Feb, bukan 2 Mar.
    expect(FinancePeriod::startFor(28, '2026-03-10')->toDateString())->toBe('2026-02-28');
    expect(FinancePeriod::endOf('2026-02-28')->toDateString())->toBe('2026-03-27');
});

it('memilih bulan akhir saat jumlah harinya seri', function () {
    // 15 Feb – 14 Mar 2026: 14 hari di Februari, 14 hari di Maret.
    $start = FinancePeriod::startFor(15, '2026-02-20');

    expect($start->toDateString())->toBe('2026-02-15');
    expect(FinancePeriod::endOf($start)->toDateString())->toBe('2026-03-14');
    expect(FinancePeriod::label($start)['title'])->toBe('Maret 2026');
});

it('menjepit reset day di luar rentang 1-28', function () {
    expect(FinancePeriod::startFor(31, '2026-01-30')->toDateString())->toBe('2026-01-28');
    expect(FinancePeriod::startFor(0, '2026-01-30')->toDateString())->toBe('2026-01-01');
});

it('menghasilkan daftar periode terbaru duluan', function () {
    $recent = FinancePeriod::recent(25, 3, '2026-08-26');

    expect(array_map(fn ($p) => $p->toDateString(), $recent))
        ->toBe(['2026-08-25', '2026-07-25', '2026-06-25']);
});
