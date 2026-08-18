<?php

namespace App\Modules\Calendar\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'user_id', 'title', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBetween(Builder $query, $from, $to): void
    {
        $query->whereBetween('starts_at', [$from, $to]);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    /** Baris kedua di kartu agenda: jam selesai, atau "Selesai" kalau acaranya sudah lewat. */
    public function endLabel(): string
    {
        if ($this->ends_at) {
            return $this->ends_at->format('H:i');
        }

        return $this->starts_at->isPast() ? 'Selesai' : '—';
    }
}
