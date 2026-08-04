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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return RecipeResource::normalizeNutrition($data);
    }

    protected function afterSave(): void
    {
        RecipeResource::syncPrimaryIngredients(
            $this->record,
            $this->data['primary_ingredient_ids'] ?? []
        );
        RecipeResource::syncIngredientQuantities(
            $this->record,
            $this->data['ingredient_quantities'] ?? []
        );
    }
}
