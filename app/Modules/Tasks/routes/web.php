<?php

use App\Modules\Tasks\Livewire\TaskList;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/tugas', TaskList::class)->name('tugas');
});
