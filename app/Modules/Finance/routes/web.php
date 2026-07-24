<?php

use App\Modules\Finance\Livewire\FinancePage;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/finance', FinancePage::class)->name('finance.index');
});
