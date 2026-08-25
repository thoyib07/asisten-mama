<?php

namespace App\Models;

use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Services\DefaultCategories;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class Household extends Model
{
    protected $fillable = ['name', 'budget_period_reset_day'];

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
            $household = new static(['name' => $name]);
            $household->forceFill(['invite_code' => static::generateUniqueInviteCode()]);
            $household->save();

            $household->users()->attach($user->id, ['role' => 'owner']);
            $user->forceFill(['current_household_id' => $household->id])->save();

            DefaultCategories::seedFor($household);

            return $household;
        });
    }

    public static function findByInviteCode(string $code): ?self
    {
        return static::where('invite_code', Str::upper(trim($code)))->first();
    }

    public static function joinWithCode(User $user, string $code): ?self
    {
        $household = static::findByInviteCode($code);

        if (! $household) {
            return null;
        }

        DB::transaction(function () use ($household, $user) {
            $household->users()->attach($user->id, ['role' => 'member']);
            $user->forceFill(['current_household_id' => $household->id])->save();
        });

        return $household;
    }

    /**
     * Token feed ICS, dibuat saat pertama kali dibutuhkan supaya household lama tidak perlu
     * migration data — household yang tidak pernah membuka panel kalender tetap tanpa token.
     */
    public function calendarToken(): string
    {
        if (! $this->calendar_token) {
            $this->forceFill(['calendar_token' => static::generateUniqueCalendarToken()])->save();
        }

        return $this->calendar_token;
    }

    public function regenerateCalendarToken(): void
    {
        $this->forceFill(['calendar_token' => static::generateUniqueCalendarToken()])->save();
    }

    public function regenerateInviteCode(): void
    {
        $this->forceFill(['invite_code' => static::generateUniqueInviteCode()])->save();
    }

    public function roleFor(User $user): ?string
    {
        return $this->users->firstWhere('id', $user->id)?->pivot->role;
    }

    public function removeMember(User $actor, User $target): void
    {
        $actorRole = $this->roleFor($actor);
        $targetRole = $this->roleFor($target);

        if ($actorRole !== 'owner' && $actor->id !== $target->id) {
            throw new RuntimeException('Cuma pemilik keluarga yang bisa mengeluarkan anggota lain.');
        }

        if ($targetRole === 'owner') {
            throw new RuntimeException('Pemilik keluarga tidak bisa dikeluarkan/keluar dari sini.');
        }

        DB::transaction(function () use ($target) {
            $this->users()->detach($target->id);

            if ($target->current_household_id === $this->id) {
                static::createWithOwner($target, "Keluarga {$target->name}");
            }
        });
    }

    private static function generateUniqueCalendarToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('calendar_token', $token)->exists());

        return $token;
    }

    private static function generateUniqueInviteCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }
}
