<?php

use App\Modules\Finance\Livewire\FinancePage;
use App\Modules\Finance\Livewire\KantongPage;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/finance', FinancePage::class)->name('finance.index');
    Route::get('/finance/kantong', KantongPage::class)->name('finance.kantong');
});
