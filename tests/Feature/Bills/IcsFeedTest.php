<?php

use App\Models\Household;
use App\Modules\Bills\Models\Bill;
use App\Modules\Bills\Models\BillPayment;
use App\Modules\Bills\Services\IcsFeed;

beforeEach(function () {
    $this->owner = makeHouseholdUser('Budi');
    auth()->login($this->owner);
    $this->household = $this->owner->currentHousehold;
    $this->feed = app(IcsFeed::class);
});

function bill(array $attributes = []): Bill
{
    return Bill::create(array_merge([
        'name' => 'Listrik PLN',
        'amount_estimate' => 450000,
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=20',
        'starts_on' => '2026-01-20',
        'reminder_days_before' => 2,
    ], $attributes));
}

it('wraps events in a valid VCALENDAR envelope with CRLF line endings', function () {
    bill();

    $ics = $this->feed->forHousehold($this->household, '2026-03-01');

    expect($ics)->toStartWith("BEGIN:VCALENDAR\r\n")
        ->and($ics)->toEndWith("END:VCALENDAR\r\n")
        ->and($ics)->toContain('VERSION:2.0')
        // Setiap baris harus diakhiri CRLF, tidak boleh ada LF telanjang (RFC 5545 §3.1).
        ->and(preg_match('/(?<!\r)\n/', $ics))->toBe(0);
});

it('places the event on the reminder date, not the due date', function () {
    bill(['reminder_days_before' => 2]);

    $ics = $this->feed->forHousehold($this->household, '2026-03-01');

    // Jatuh tempo 20 Maret, pengingat 2 hari sebelumnya = 18 Maret.
    expect($ics)->toContain('DTSTART;VALUE=DATE:20260318')
        ->and($ics)->not->toContain('DTSTART;VALUE=DATE:20260320');
});

it('names the due date in the summary so the event is self-explanatory', function () {
    bill(['reminder_days_before' => 2]);

    $ics = $this->feed->forHousehold($this->household, '2026-03-01');

    expect($ics)->toContain('Listrik PLN')
        ->and($ics)->toContain('20 Mar');
});

it('emits one event per occurrence instead of a recurrence rule', function () {
    bill();

    $ics = $this->feed->forHousehold($this->household, '2026-03-01', '2026-05-31');

    // Occurrence diekspansi di PHP oleh BillSchedule, bukan diserahkan ke Google sebagai
    // RRULE — supaya penggeseran ke tanggal pengingat benar untuk RRULE apa pun.
    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(3)
        ->and($ics)->not->toContain('RRULE:');
});

it('gives each occurrence a stable unique id', function () {
    $created = bill();

    $ics = $this->feed->forHousehold($this->household, '2026-03-01', '2026-04-30');

    expect($ics)->toContain("UID:bill-{$created->id}-20260320@asisten-mama")
        ->and($ics)->toContain("UID:bill-{$created->id}-20260420@asisten-mama");
});

it('omits occurrences that have already been paid', function () {
    $created = bill();

    BillPayment::create([
        'bill_id' => $created->id,
        'period_on' => '2026-04-20',
        'amount' => 430000,
        'user_id' => $this->owner->id,
        'paid_at' => now(),
    ]);

    $ics = $this->feed->forHousehold($this->household, '2026-03-01', '2026-05-31');

    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(2)
        ->and($ics)->not->toContain('20260418');
});

it('omits archived bills', function () {
    bill(['name' => 'Masih aktif']);
    $old = bill(['name' => 'Sudah diarsip']);
    $old->forceFill(['archived_at' => now()])->save();

    $ics = $this->feed->forHousehold($this->household, '2026-03-01', '2026-03-31');

    expect($ics)->toContain('Masih aktif')
        ->and($ics)->not->toContain('Sudah diarsip');
});

it('escapes characters that would otherwise corrupt the feed', function () {
    // Nama tagihan diketik user: satu koma yang lolos merusak seluruh feed di sisi Google.
    bill(['name' => 'Listrik, air; token\lama', 'notes' => "baris satu\nbaris dua"]);

    $ics = $this->feed->forHousehold($this->household, '2026-03-01', '2026-03-31');

    expect($ics)->toContain('Listrik\, air\; token\\\\lama')
        ->and($ics)->toContain('baris satu\nbaris dua');
});

it('folds lines longer than 75 octets', function () {
    bill(['name' => str_repeat('Tagihan Panjang ', 10)]);

    $ics = $this->feed->forHousehold($this->household, '2026-03-01', '2026-03-31');

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});

it('folds without splitting a multi-byte character', function () {
    // Emoji di judul: melipat di tengah byte UTF-8 menghasilkan karakter rusak.
    bill(['name' => str_repeat('🧾', 50)]);

    $ics = $this->feed->forHousehold($this->household, '2026-03-01', '2026-03-31');

    $unfolded = str_replace("\r\n ", '', $ics);

    expect(mb_check_encoding($unfolded, 'UTF-8'))->toBeTrue()
        ->and($unfolded)->toContain(str_repeat('🧾', 50));
});

it('never includes bills belonging to another household', function () {
    bill(['name' => 'Tagihan Budi']);

    $other = makeHouseholdUser('Siti');
    auth()->login($other);
    bill(['name' => 'Tagihan Siti']);

    // Feed dibangun tanpa user terautentikasi (token yang jadi auth), jadi global scope
    // BelongsToHousehold tidak bisa diandalkan di sini.
    auth()->logout();

    $ics = $this->feed->forHousehold(Household::find($this->household->id), '2026-03-01', '2026-03-31');

    expect($ics)->toContain('Tagihan Budi')
        ->and($ics)->not->toContain('Tagihan Siti');
});

it('still omits paid occurrences when nobody is authenticated', function () {
    $created = bill();

    BillPayment::create([
        'bill_id' => $created->id,
        'period_on' => '2026-04-20',
        'amount' => 430000,
        'user_id' => $this->owner->id,
        'paid_at' => now(),
    ]);

    // Feed ICS dilayani lewat token, tanpa sesi. Kalau pembacaan riwayat pembayaran ikut
    // kena global scope BelongsToHousehold, daftar lunas jadi kosong dan tagihan yang sudah
    // dibayar muncul lagi di kalender keluarga.
    auth()->logout();

    $ics = $this->feed->forHousehold(Household::find($this->household->id), '2026-03-01', '2026-05-31');

    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(2)
        ->and($ics)->not->toContain('20260418');
});
