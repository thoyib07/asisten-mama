<?php

namespace App\Modules\Calendar\Livewire;

use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class CalendarPage extends Component
{
    /** Bulan yang sedang ditampilkan, format Y-m. */
    public string $month = '';

    /** Tanggal yang agendanya ditampilkan di bawah grid, format Y-m-d. */
    public string $selected = '';

    public bool $adding = false;

    public string $title = '';

    public ?int $userId = null;

    public string $startsAt = '';

    public string $endsAt = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->selected = now()->toDateString();
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->cursor()->addMonths($by)->format('Y-m');
    }

    public function selectDay(string $date): void
    {
        $this->selected = $date;
    }

    public function toggleForm(): void
    {
        $this->adding = ! $this->adding;

        if ($this->adding) {
            $this->userId = auth()->id();
            // Jam default 08:00 pada tanggal yang sedang dipilih — bukan "sekarang", supaya
            // menambah acara untuk tanggal lain tidak perlu mengetik ulang tanggalnya.
            $this->startsAt = Carbon::parse($this->selected)->setTime(8, 0)->format('Y-m-d\TH:i');
        }
    }

    public function addEvent(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'userId' => ['nullable', 'integer'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
        ], [
            'endsAt.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        $event = Event::create([
            'user_id' => $this->memberIds()->contains($data['userId']) ? $data['userId'] : null,
            'title' => $data['title'],
            'starts_at' => $data['startsAt'],
            'ends_at' => $data['endsAt'] ?: null,
        ]);

        // Lompat ke tanggal acara baru supaya hasilnya langsung kelihatan, meski acaranya
        // dibuat untuk bulan lain.
        $this->selected = $event->starts_at->toDateString();
        $this->month = $event->starts_at->format('Y-m');

        $this->reset('title', 'startsAt', 'endsAt', 'adding');
    }

    public function removeEvent(int $eventId): void
    {
        Event::where('id', $eventId)->delete();
    }

    private function cursor(): Carbon
    {
        // Hari & jam ditulis eksplisit: createFromFormat('Y-m', ...) mengisi bagian yang hilang
        // dari waktu sekarang, jadi pada tanggal 31 bulan Februari meluber ke Maret.
        return Carbon::createFromFormat('Y-m-d H:i:s', $this->month.'-01 00:00:00');
    }

    private function memberIds()
    {
        return auth()->user()->currentHousehold->users->pluck('id');
    }

    public function render()
    {
        $cursor = $this->cursor();
        // Senin-dulu, cocok dengan header ['S','S','R','K','J','S','M'] di blade. Start dan end
        // harus jadi pasangan (MONDAY/SUNDAY) — kalau keduanya SUNDAY jumlah selnya selalu
        // 1 (mod 7) dan grid 7 kolom menyisakan satu sel yatim di baris ke-7 setiap bulan.
        $gridStart = $cursor->copy()->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $cursor->copy()->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $monthEvents = Event::with('user')->between($gridStart, $gridEnd)->get();

        return view('livewire.calendar.calendar-page', [
            'cursor' => $cursor,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            // Titik indikator per tanggal: kelas warna avatar pemilik acara, unik per hari.
            'marks' => $monthEvents->groupBy(fn ($e) => $e->starts_at->toDateString())
                ->map(fn ($events) => $events
                    ->map(fn ($e) => $e->user?->avatarColorClass() ?? 'bg-accent')
                    ->unique()->take(4)->values()),
            'agenda' => $monthEvents
                ->filter(fn ($e) => $e->starts_at->toDateString() === $this->selected)
                ->sortBy('starts_at')->values(),
            'members' => auth()->user()->currentHousehold->users,
        ]);
    }
}
