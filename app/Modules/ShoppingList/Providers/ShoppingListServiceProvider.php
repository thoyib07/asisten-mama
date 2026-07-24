<?php

namespace App\Modules\ShoppingList\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ShoppingListServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');

        Livewire::addNamespace('shopping-list', classNamespace: 'App\\Modules\\ShoppingList\\Livewire');
    }
}
