<?php

use App\Modules\Household\Livewire\HouseholdPage;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/keluarga', HouseholdPage::class)->name('household.index');
});
