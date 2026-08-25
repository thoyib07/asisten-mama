<?php

use App\Models\User;
use App\Modules\Calendar\Livewire\CalendarPage;
use App\Modules\Calendar\Services\FamilyIcsFeed;
use Illuminate\Support\Facades\Route;

/*
 * Feed kalender sengaja di luar middleware `auth` — Google mengambilnya sebagai server, tanpa
 * sesi. Tokennya yang jadi autentikasi, dan bisa dicabut lewat regenerate. Pola yang sama
 * dengan feed Tagihan, tapi tokennya per-user karena isinya termasuk tugas milik user itu.
 */
Route::get('/kalender/{token}.ics', function (string $token, FamilyIcsFeed $feed) {
    $user = User::where('calendar_token', $token)->firstOrFail();

    return response($feed->forUser($user))
        ->header('Content-Type', 'text/calendar; charset=utf-8');
})->where('token', '[A-Za-z0-9]+')->name('kalender.feed');

Route::middleware('auth')->group(function () {
    Route::get('/kalender', CalendarPage::class)->name('kalender');
});
