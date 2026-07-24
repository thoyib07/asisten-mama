<?php

namespace App\Modules\Cooking\Livewire;

use App\Modules\Cooking\Models\Recipe;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class RecipeList extends Component
{
    private const PER_PAGE_STEP = 12;

    public string $search = '';

    public bool $onlyFavorites = false;

    public int $perPage = self::PER_PAGE_STEP;

    protected bool $lockFavoritesFilter = false;

    public function updatedSearch(): void
    {
        $this->perPage = self::PER_PAGE_STEP;
    }

    public function updatedOnlyFavorites(): void
    {
        $this->perPage = self::PER_PAGE_STEP;
    }

    public function loadMore(): void
    {
        $this->perPage += self::PER_PAGE_STEP;
    }

    public function render()
    {
        $query = Recipe::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'ilike', '%'.$this->search.'%'))
            ->when($this->onlyFavorites, fn ($q) => $q->favoritedBy(auth()->id()))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $total = $query->count();
        $recipes = $query->take($this->perPage)->get();

        return view('livewire.cooking.recipe-list', [
            'recipes' => $recipes,
            'hasMore' => $total > $this->perPage,
            'lockFavoritesFilter' => $this->lockFavoritesFilter,
        ]);
    }
}
