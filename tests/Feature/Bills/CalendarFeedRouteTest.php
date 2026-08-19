<?php

use App\Modules\Bills\Models\Bill;

beforeEach(function () {
    $this->owner = makeHouseholdUser('Budi');
    auth()->login($this->owner);
    $this->household = $this->owner->currentHousehold;

    Bill::create([
        'name' => 'Listrik PLN',
        'amount_estimate' => 450000,
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=20',
        'starts_on' => now()->startOfMonth()->toDateString(),
        'reminder_days_before' => 2,
    ]);
});

it('serves the calendar to anyone holding the token, without a session', function () {
    $token = $this->household->calendarToken();

    // Token itu sendiri yang jadi autentikasi — Google mengambil feed tanpa sesi login.
    auth()->logout();

    $this->get("/tagihan/kalender/{$token}.ics")
        ->assertOk()
        ->assertHeader('content-type', 'text/calendar; charset=utf-8')
        ->assertSee('BEGIN:VCALENDAR', escape: false)
        ->assertSee('Listrik PLN', escape: false);
});

it('returns 404 for an unknown token', function () {
    auth()->logout();

    $this->get('/tagihan/kalender/'.str_repeat('x', 48).'.ics')->assertNotFound();
});

it('invalidates the old url once the token is regenerated', function () {
    $old = $this->household->calendarToken();
    $this->household->regenerateCalendarToken();
    $new = $this->household->fresh()->calendar_token;

    auth()->logout();

    expect($new)->not->toBe($old);
    $this->get("/tagihan/kalender/{$old}.ics")->assertNotFound();
    $this->get("/tagihan/kalender/{$new}.ics")->assertOk();
});

it('serves only the bills of the household owning the token', function () {
    $token = $this->household->calendarToken();

    $other = makeHouseholdUser('Siti');
    auth()->login($other);
    Bill::create([
        'name' => 'Internet Siti',
        'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=20',
        'starts_on' => now()->startOfMonth()->toDateString(),
        'reminder_days_before' => 2,
    ]);
    $otherToken = $other->currentHousehold->calendarToken();

    auth()->logout();

    $this->get("/tagihan/kalender/{$token}.ics")
        ->assertSee('Listrik PLN', escape: false)
        ->assertDontSee('Internet Siti', escape: false);

    $this->get("/tagihan/kalender/{$otherToken}.ics")
        ->assertSee('Internet Siti', escape: false)
        ->assertDontSee('Listrik PLN', escape: false);
});
