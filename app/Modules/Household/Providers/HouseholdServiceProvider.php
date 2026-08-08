<?php

namespace App\Modules\Household\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class HouseholdServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');

        Livewire::addNamespace('household', classNamespace: 'App\\Modules\\Household\\Livewire');
    }
}
