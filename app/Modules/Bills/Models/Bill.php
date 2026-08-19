<?php

namespace App\Modules\Bills\Models;

use App\Modules\Finance\Models\Category;
use App\Support\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use BelongsToHousehold;

    protected $fillable = [
        'household_id', 'category_id', 'name', 'amount_estimate',
        'rrule', 'starts_on', 'reminder_days_before', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'archived_at' => 'datetime',
            'amount_estimate' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
