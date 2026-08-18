<?php

namespace App\Modules\ShoppingList\Livewire;

use App\Modules\ShoppingList\Models\ShoppingList;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class ShoppingListPage extends Component
{
    public string $newItem = '';

    public function addItem(): void
    {
        $name = trim($this->newItem);
        if ($name === '') {
            return;
        }

        $list = $this->currentList();
        $list->items()->create([
            'name' => $name,
            'added_by' => auth()->id(),
        ]);

        $this->newItem = '';
    }

    public function toggleItem(int $itemId): void
    {
        $item = $this->currentList()->items()->findOrFail($itemId);
        $item->update(['is_checked' => ! $item->is_checked]);
    }

    public function removeItem(int $itemId): void
    {
        $this->currentList()->items()->where('id', $itemId)->delete();
    }

    private function currentList(): ShoppingList
    {
        return ShoppingList::firstOrCreate(['household_id' => auth()->user()->current_household_id]);
    }

    public function render()
    {
        $items = $this->currentList()->items()->with('addedBy')
            ->orderBy('is_checked')->orderByDesc('id')->get();

        return view('livewire.shopping-list.shopping-list-page', ['items' => $items]);
    }
}
