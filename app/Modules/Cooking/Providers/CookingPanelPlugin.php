<?php

namespace App\Modules\Cooking\Providers;

use Filament\Contracts\Plugin;
use Filament\Panel;

class CookingPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'cooking';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: app_path('Modules/Cooking/Filament/Resources'),
            for: 'App\\Modules\\Cooking\\Filament\\Resources',
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
