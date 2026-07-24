<?php

namespace App\Modules\Finance\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FinanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');

        Livewire::addNamespace('finance', classNamespace: 'App\\Modules\\Finance\\Livewire');
    }
}
