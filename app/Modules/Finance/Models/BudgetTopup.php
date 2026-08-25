<?php

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Insert-only: tambahan nominal ke kantong di tengah periode, tidak pernah diubah/dihapus. */
class BudgetTopup extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'budget_allocation_id', 'user_id', 'amount', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
