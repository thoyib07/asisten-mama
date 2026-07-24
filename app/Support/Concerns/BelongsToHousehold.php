<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToHousehold
{
    protected static function bootBelongsToHousehold(): void
    {
        static::addGlobalScope('household', function (Builder $query) {
            $query->where(
                $query->getModel()->getTable().'.household_id',
                Auth::user()?->current_household_id
            );
        });

        static::creating(function ($model) {
            $model->household_id ??= Auth::user()?->current_household_id;
        });
    }
}
