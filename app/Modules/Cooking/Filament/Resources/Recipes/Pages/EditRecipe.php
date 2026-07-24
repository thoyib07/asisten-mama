<?php

namespace App\Modules\Cooking\Filament\Resources\Recipes\Pages;

use App\Modules\Cooking\Filament\Resources\Recipes\RecipeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecipe extends EditRecord
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
