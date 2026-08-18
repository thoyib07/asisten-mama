<?php

use App\Modules\Calendar\Livewire\CalendarPage;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/kalender', CalendarPage::class)->name('kalender');
});
