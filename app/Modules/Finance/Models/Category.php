<?php

namespace App\Modules\Finance\Models;

use App\Support\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToHousehold;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    protected $fillable = ['household_id', 'type', 'name', 'icon'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
