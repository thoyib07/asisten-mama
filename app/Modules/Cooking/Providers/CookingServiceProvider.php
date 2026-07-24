<?php

namespace App\Modules\Cooking\Providers;

use App\Modules\Cooking\Services\Ai\AiRecipeClient;
use App\Modules\Cooking\Services\Groq\GroqRecipeClient;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiRecipeClient::class, GroqRecipeClient::class);
    }

    public function boot(): void
    {
        // loadRoutesFrom() alone doesn't inherit the 'web' middleware group —
        // that's only auto-applied to the file passed to withRouting(), so
        // module route files must opt in explicitly or session/auth/CSRF break.
        Route::middleware('web')->group(__DIR__.'/../routes/web.php');

        Livewire::addNamespace('cooking', classNamespace: 'App\\Modules\\Cooking\\Livewire');
    }
}
