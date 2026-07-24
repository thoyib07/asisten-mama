<?php

namespace App\Models;

use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Services\DefaultCategories;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Household extends Model
{
    protected $fillable = ['name'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'household_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public static function createWithOwner(User $user, string $name): self
    {
        return DB::transaction(function () use ($user, $name) {
            $household = static::create(['name' => $name]);

            $household->users()->attach($user->id, ['role' => 'owner']);
            $user->forceFill(['current_household_id' => $household->id])->save();

            DefaultCategories::seedFor($household);

            return $household;
        });
    }
}
