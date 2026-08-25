<?php

use App\Models\User;
use App\Modules\Calendar\Models\Event;
use App\Modules\Tasks\Models\Task;

beforeEach(function () {
    $this->budi = makeHouseholdUser('Budi');
    auth()->login($this->budi);
    $this->household = $this->budi->currentHousehold;

    $this->siti = User::factory()->create(['name' => 'Siti']);
    $this->household->users()->attach($this->siti->id, ['role' => 'member']);
    $this->siti->forceFill(['current_household_id' => $this->household->id])->save();
});

/**
 * Ambil feed lalu buka lipatan barisnya. Assertion terhadap ICS mentah bisa lolos palsu kalau
 * teks yang dicari kebetulan terpotong di batas 75 oktet — sama seperti tests/Feature/Bills.
 */
function feedFor(string $token): string
{
    return str_replace("\r\n ", '', test()->get("/kalender/{$token}.ics")->getContent());
}

function makeEvent(array $attributes = []): Event
{
    return Event::create(array_merge([
        'title' => 'Rapat sekolah',
        'starts_at' => now()->addDays(3)->setTime(9, 0),
        'ends_at' => now()->addDays(3)->setTime(11, 0),
    ], $attributes));
}

function makeTask(array $attributes = []): Task
{
    return Task::create(array_merge([
        'title' => 'Cuci piring',
        'due_on' => now()->addDays(2)->toDateString(),
        'priority' => 'sedang',
        'is_done' => false,
    ], $attributes));
}

it('wraps everything in a valid VCALENDAR envelope with CRLF line endings', function () {
    makeEvent();

    $ics = $this->get('/kalender/'.$this->budi->calendarToken().'.ics')->getContent();

    expect($ics)->toStartWith("BEGIN:VCALENDAR\r\n")
        ->and($ics)->toEndWith("END:VCALENDAR\r\n")
        // Nama household sudah berawalan "Keluarga"; jangan sampai jadi "Keluarga Keluarga Budi".
        ->and($ics)->toContain('X-WR-CALNAME:Keluarga Budi')
        ->and($ics)->not->toContain('Keluarga Keluarga')
        // Setiap baris harus diakhiri CRLF, tidak boleh ada LF telanjang (RFC 5545 §3.1).
        ->and(preg_match('/(?<!\r)\n/', $ics))->toBe(0);
});

it('serves the household agenda plus only the token owner tasks, without a session', function () {
    makeEvent(['title' => 'Rapat sekolah', 'user_id' => $this->siti->id]);
    makeTask(['title' => 'Cuci piring', 'user_id' => $this->budi->id]);
    makeTask(['title' => 'Setrika baju', 'user_id' => $this->siti->id]);

    $token = $this->budi->calendarToken();

    // Token itu sendiri yang jadi autentikasi — Google mengambil feed tanpa sesi login.
    auth()->logout();

    $this->get("/kalender/{$token}.ics")
        ->assertOk()
        ->assertHeader('content-type', 'text/calendar; charset=utf-8');

    $ics = feedFor($token);

    // Agenda keluarga tampil untuk semua anggota, lengkap dengan penanggung jawabnya.
    expect($ics)->toContain('Rapat sekolah')
        ->and($ics)->toContain('Siti')
        ->and($ics)->toContain('Cuci piring')
        // Tugas anggota lain tidak ikut memenuhi kalender pribadi.
        ->and($ics)->not->toContain('Setrika baju');
});

it('leaves out done tasks and tasks without a due date', function () {
    makeTask(['title' => 'Sudah beres', 'user_id' => $this->budi->id, 'is_done' => true]);
    makeTask(['title' => 'Tanpa tanggal', 'user_id' => $this->budi->id, 'due_on' => null]);
    makeTask(['title' => 'Masih ada', 'user_id' => $this->budi->id]);

    $token = $this->budi->calendarToken();
    auth()->logout();

    $ics = feedFor($token);

    expect($ics)->toContain('Masih ada')
        ->and($ics)->not->toContain('Sudah beres')
        ->and($ics)->not->toContain('Tanpa tanggal');
});

it('emits timed events as floating local time and tasks as all-day', function () {
    $starts = now()->addDays(3)->setTime(9, 0);
    makeEvent(['starts_at' => $starts, 'ends_at' => null, 'user_id' => null]);
    makeTask(['user_id' => $this->budi->id, 'due_on' => now()->addDays(2)->toDateString()]);

    $token = $this->budi->calendarToken();
    auth()->logout();

    $ics = $this->get("/kalender/{$token}.ics")->getContent();

    // Tanpa akhiran Z: menandainya UTC akan menggeser acara 7 jam di sisi Google.
    expect($ics)->toContain('DTSTART:'.$starts->format('Ymd\THis'))
        ->and($ics)->not->toContain('DTSTART:'.$starts->format('Ymd\THis').'Z')
        // Acara tanpa jam selesai diberi durasi satu jam supaya tidak jadi acara nol-menit.
        ->and($ics)->toContain('DTEND:'.$starts->copy()->addHour()->format('Ymd\THis'))
        ->and($ics)->toContain('DTSTART;VALUE=DATE:'.now()->addDays(2)->format('Ymd'))
        // Akhir all-day bersifat eksklusif.
        ->and($ics)->toContain('DTEND;VALUE=DATE:'.now()->addDays(3)->format('Ymd'));
});

it('never leaks another household agenda', function () {
    makeEvent(['title' => 'Rapat sekolah']);
    $token = $this->budi->calendarToken();

    $outsider = makeHouseholdUser('Ani');
    auth()->login($outsider);
    makeEvent(['title' => 'Arisan Ani']);
    $outsiderToken = $outsider->calendarToken();

    auth()->logout();

    $mine = feedFor($token);
    $theirs = feedFor($outsiderToken);

    expect($mine)->toContain('Rapat sekolah')
        ->and($mine)->not->toContain('Arisan Ani')
        ->and($theirs)->toContain('Arisan Ani')
        ->and($theirs)->not->toContain('Rapat sekolah');
});

it('returns 404 for an unknown token', function () {
    auth()->logout();

    $this->get('/kalender/'.str_repeat('x', 48).'.ics')->assertNotFound();
});

it('invalidates the old url once the token is regenerated', function () {
    $old = $this->budi->calendarToken();
    $this->budi->regenerateCalendarToken();
    $new = $this->budi->fresh()->calendar_token;

    auth()->logout();

    expect($new)->not->toBe($old);
    $this->get("/kalender/{$old}.ics")->assertNotFound();
    $this->get("/kalender/{$new}.ics")->assertOk();
});

it('keeps handing back the same lazily generated token', function () {
    // Kalau token diputar tiap kali dipanggil, tiap render halaman akan mematikan langganan
    // yang sudah dipasang anggota keluarga di Google.
    expect($this->budi->calendarToken())->toBe($this->budi->calendarToken())
        ->and($this->budi->fresh()->calendar_token)->toBe($this->budi->calendarToken());
});

it('serves an empty calendar, not a leak, for a user without a household', function () {
    makeEvent(['title' => 'Rapat sekolah']);

    $nomad = User::factory()->create(['name' => 'Nomad']);
    $token = $nomad->calendarToken();

    auth()->logout();

    // household_id null memfilter `IS NULL`, dan kolomnya NOT NULL di events/tasks — gagal
    // menutup, bukan gagal membuka. CLAUDE.md: jalur feed tanpa sesi wajib fail closed.
    $this->get("/kalender/{$token}.ics")->assertOk();

    $ics = feedFor($token);

    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->and($ics)->not->toContain('BEGIN:VEVENT')
        ->and($ics)->not->toContain('Rapat sekolah');
});

it('still carries a long-overdue task that is not done yet', function () {
    // Feed adalah keadaan lengkap kalender tiap kali diambil, jadi batas bawah jendela akan
    // menghapus justru tugas yang paling tidak boleh hilang dari kalender seseorang.
    makeTask(['title' => 'Telat lama', 'user_id' => $this->budi->id,
        'due_on' => now()->subMonths(8)->toDateString()]);

    $token = $this->budi->calendarToken();
    auth()->logout();

    expect(feedFor($token))->toContain('Telat lama');
});
