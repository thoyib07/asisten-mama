<?php

namespace App\Modules\Cooking\Livewire;

use App\Modules\Cooking\Models\Favorite;
use Livewire\Component;

class FavoriteButton extends Component
{
    public int $recipeId;

    public bool $isFavorited = false;

    public function mount(int $recipeId): void
    {
        $this->recipeId = $recipeId;
        $this->refreshState();
    }

    public function toggle(): void
    {
        if (! auth()->check()) {
            return;
        }

        $userId = auth()->id();
        $existing = Favorite::where('recipe_id', $this->recipeId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            Favorite::create(['recipe_id' => $this->recipeId, 'user_id' => $userId]);
        }

        $this->refreshState();
    }

    private function refreshState(): void
    {
        $this->isFavorited = Favorite::where('recipe_id', $this->recipeId)
            ->where('user_id', auth()->id())
            ->exists();
    }

    public function render()
    {
        return view('livewire.cooking.favorite-button');
    }
}
