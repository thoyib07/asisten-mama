<?php

namespace App\Modules\Cooking\Filament\Resources\Recipes\Pages;

use App\Modules\Cooking\Filament\Resources\Recipes\RecipeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecipe extends CreateRecord
{
    protected static string $resource = RecipeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return RecipeResource::normalizeNutrition($data);
    }

    protected function afterCreate(): void
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
