<?php

namespace App\Modules\ShoppingList\Models;

use App\Models\User;
use App\Modules\Cooking\Models\Ingredient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingListItem extends Model
{
    protected $fillable = ['shopping_list_id', 'ingredient_id', 'name', 'quantity', 'is_checked', 'added_by'];

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
        ];
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
