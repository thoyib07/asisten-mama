<?php

namespace App\Filament\Widgets;

use App\Models\Household;
use App\Models\User;
use App\Modules\Cooking\Models\Recipe;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SaasOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $recipes = Recipe::count();
        $aiRecipes = Recipe::where('source', Recipe::SOURCE_AI)->count();

        return [
            Stat::make('Keluarga terdaftar', Household::count()),
            Stat::make('Customer terdaftar', User::count()),
            Stat::make('Resep', $recipes)
                ->description("{$aiRecipes} hasil AI")
                ->descriptionIcon('heroicon-m-sparkles'),
        ];
    }
}
