<?php

namespace App\Modules\Tasks\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use BelongsToHousehold;

    public const PRIORITIES = ['tinggi' => 'Tinggi', 'sedang' => 'Sedang', 'rendah' => 'Rendah'];

    /** Kelas badge per prioritas — docs/ui-design.md §4. */
    public const PRIORITY_BADGES = [
        'tinggi' => 'badge-danger',
        'sedang' => 'badge-warning',
        'rendah' => 'badge-muted',
    ];

    protected $fillable = ['household_id', 'user_id', 'title', 'due_on', 'priority', 'is_done'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'is_done' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('is_done', false);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function priorityBadgeClass(): string
    {
        return self::PRIORITY_BADGES[$this->priority] ?? 'badge-muted';
    }

    /** Badge jadwal di kartu tugas: "Hari Ini" / "Besok" / tanggal pendek. */
    public function dueLabel(): ?string
    {
        if (! $this->due_on) {
            return null;
        }

        return match (true) {
            $this->due_on->isToday() => 'Hari Ini',
            $this->due_on->isTomorrow() => 'Besok',
            default => $this->due_on->locale('id')->translatedFormat('j M'),
        };
    }
}
