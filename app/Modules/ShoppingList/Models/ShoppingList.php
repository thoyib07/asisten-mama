<?php

namespace App\Modules\ShoppingList\Models;

use App\Support\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingList extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'name'];

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class);
    }
}
