<?php

namespace App\Modules\Finance\Models;

use App\Support\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToHousehold;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    protected $fillable = ['household_id', 'type', 'name', 'icon', 'is_default', 'archived_at'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    /** Kategori yang masih boleh dipilih untuk transaksi/kantong baru. */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /** 7 kategori seed tidak bisa diarsipkan — supaya tiap household selalu punya baseline. */
    public function canBeArchived(): bool
    {
        return ! $this->is_default;
    }
}
