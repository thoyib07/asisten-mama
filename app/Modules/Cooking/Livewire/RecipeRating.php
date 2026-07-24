<?php

namespace App\Modules\Cooking\Livewire;

use App\Modules\Cooking\Models\Rating;
use App\Modules\Cooking\Models\Recipe;
use Livewire\Component;

class RecipeRating extends Component
{
    public Recipe $recipe;

    public float $average = 0.0;

    public int $count = 0;

    public bool $hasRated = false;

    public function mount(Recipe $recipe): void
    {
        $this->recipe = $recipe;
        $this->refreshStats();
        $this->hasRated = Rating::where('recipe_id', $recipe->id)
            ->where('user_id', auth()->id())->exists();
    }

    public function rate(int $value): void
    {
        if (! auth()->check() || $this->hasRated) {
            return;
        }

        $value = max(1, min(5, $value));
        Rating::create([
            'recipe_id' => $this->recipe->id,
            'value' => $value,
            'user_id' => auth()->id(),
        ]);
        $this->hasRated = true;
        $this->refreshStats();
    }

    private function refreshStats(): void
    {
        $stats = Rating::where('recipe_id', $this->recipe->id)
            ->selectRaw('COUNT(*) as cnt, COALESCE(AVG(value), 0) as avg')
            ->first();
        $this->count = (int) $stats->cnt;
        $this->average = round((float) $stats->avg, 1);
    }

    public function render()
    {
        return view('livewire.cooking.recipe-rating');
    }
}
