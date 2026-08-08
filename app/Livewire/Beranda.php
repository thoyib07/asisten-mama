<?php

namespace App\Livewire;

use App\Modules\Cooking\Models\Recipe;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class Beranda extends Component
{
    public function render()
    {
        $household = auth()->user()->currentHousehold()->with('users')->first();

        return view('livewire.beranda', [
            'greeting' => $this->greeting(),
            'today' => now()->locale('id')->translatedFormat('l, d F Y'),
            'members' => $household->users,
            'newRecipeCount' => Recipe::where('created_at', '>=', now()->subDays(7))->count(),
            // ponytail: goes through the parent ShoppingList (household-scoped) rather than
            // querying ShoppingListItem directly — the item itself isn't BelongsToHousehold.
            'pendingShoppingCount' => ShoppingListItem::whereHas('shoppingList')
                ->where('is_checked', false)->count(),
        ]);
    }

    private function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 10 => 'Selamat pagi',
            $hour < 15 => 'Selamat siang',
            $hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }
}
