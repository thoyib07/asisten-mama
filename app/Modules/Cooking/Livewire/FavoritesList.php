<?php

namespace App\Modules\Cooking\Livewire;

use Livewire\Attributes\Layout;

#[Layout('components.layout')]
class FavoritesList extends RecipeList
{
    public bool $onlyFavorites = true;

    protected bool $lockFavoritesFilter = true;
}
