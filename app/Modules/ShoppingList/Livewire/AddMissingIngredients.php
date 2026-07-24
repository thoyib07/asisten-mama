<?php

namespace App\Modules\ShoppingList\Livewire;

use App\Modules\ShoppingList\Services\MissingIngredientsToShoppingList;
use Livewire\Component;

class AddMissingIngredients extends Component
{
    public int $recipeId;

    public array $missing = [];

    public bool $added = false;

    public function add(MissingIngredientsToShoppingList $service): void
    {
        $household = auth()->user()?->currentHousehold;
        if (! $household || empty($this->missing)) {
            return;
        }

        $service->add($household, $this->missing);
        $this->added = true;
    }

    public function render()
    {
        return view('livewire.shopping-list.add-missing-ingredients');
    }
}
