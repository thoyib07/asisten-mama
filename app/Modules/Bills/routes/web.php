<?php

use App\Models\Household;
use App\Modules\Bills\Livewire\BillList;
use App\Modules\Bills\Services\IcsFeed;
use Illuminate\Support\Facades\Route;

/*
 * Feed kalender sengaja di luar middleware `auth` — Google mengambilnya sebagai server,
 * tanpa sesi. Tokennya yang jadi autentikasi, dan bisa dicabut lewat regenerate.
 */
Route::get('/tagihan/kalender/{token}.ics', function (string $token, IcsFeed $feed) {
    $household = Household::where('calendar_token', $token)->firstOrFail();

    return response($feed->forHousehold($household))
        ->header('Content-Type', 'text/calendar; charset=utf-8');
})->where('token', '[A-Za-z0-9]+')->name('tagihan.kalender');

Route::middleware('auth')->group(function () {
    Route::get('/tagihan', BillList::class)->name('tagihan');
});
