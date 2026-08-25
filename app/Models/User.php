<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->current_household_id !== null;
    }

    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function currentHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'current_household_id');
    }

    /**
     * Token feed ICS pribadi, dibuat saat pertama kali dibutuhkan supaya user lama tidak perlu
     * migration data — pola yang sama dengan Household::calendarToken().
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

    public function avatarColorClass(): string
    {
        $classes = ['avatar-1', 'avatar-2', 'avatar-3', 'avatar-4', 'avatar-5', 'avatar-6'];

        return $classes[$this->id % count($classes)];
    }

    private static function generateUniqueCalendarToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('calendar_token', $token)->exists());

        return $token;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
