<?php

namespace App\Modules\Calendar\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CalendarServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');

        Livewire::addNamespace('calendar', classNamespace: 'App\Modules\Calendar\Livewire');
    }
}
