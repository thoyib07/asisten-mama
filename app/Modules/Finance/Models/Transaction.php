<?php

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use BelongsToHousehold;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    protected $fillable = ['household_id', 'category_id', 'user_id', 'type', 'amount', 'description', 'occurred_on'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeIncome(Builder $query): void
    {
        $query->where('type', self::TYPE_INCOME);
    }

    public function scopeExpense(Builder $query): void
    {
        $query->where('type', self::TYPE_EXPENSE);
    }
}
