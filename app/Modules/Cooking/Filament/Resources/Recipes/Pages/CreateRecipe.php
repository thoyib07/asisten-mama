<?php

namespace App\Modules\Cooking\Filament\Resources\Recipes\Pages;

use App\Modules\Cooking\Filament\Resources\Recipes\RecipeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecipe extends CreateRecord
{
    protected static string $resource = RecipeResource::class;
}
