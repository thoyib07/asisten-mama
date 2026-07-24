<?php

use App\Modules\ShoppingList\Livewire\ShoppingListPage;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/shopping-list', ShoppingListPage::class)->name('shopping-list.index');
});
