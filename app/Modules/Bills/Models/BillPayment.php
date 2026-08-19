<?php

namespace App\Modules\Bills\Models;

use App\Models\User;
use App\Modules\Finance\Models\Transaction;
use App\Support\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Insert-only: satu baris per occurrence tagihan yang dibayar. Pola yang sama dengan
 * rencana budget_topups di docs/prd/finance.md §6.7 — tidak pernah di-update.
 */
class BillPayment extends Model
{
    use BelongsToHousehold;

    protected $fillable = [
        'household_id', 'bill_id', 'period_on', 'amount',
        'user_id', 'transaction_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'period_on' => 'date',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
