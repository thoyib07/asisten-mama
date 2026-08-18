<div>
    @php $selectedDate = \Carbon\Carbon::parse($selected); @endphp

    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">Kalender Keluarga</h1>
            <p class="text-sm text-ink-soft">{{ $cursor->locale('id')->translatedFormat('F Y') }}</p>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="shiftMonth(-1)"
                    class="card flex h-9 w-9 items-center justify-center text-ink-soft" aria-label="Bulan sebelumnya">&lsaquo;</button>
            <button type="button" wire:click="shiftMonth(1)"
                    class="card flex h-9 w-9 items-center justify-center text-ink-soft" aria-label="Bulan berikutnya">&rsaquo;</button>
        </div>
    </div>

    <div class="card mt-4 p-4">
        <div class="grid grid-cols-7 text-center text-xs font-semibold text-ink-soft">
            @foreach (['S', 'S', 'R', 'K', 'J', 'S', 'M'] as $header)
                <span class="py-2">{{ $header }}</span>
            @endforeach
        </div>
        <div class="grid grid-cols-7 gap-y-1 text-center text-sm">
            @for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay())
                @php
                    $key = $day->toDateString();
                    $inMonth = $day->month === $cursor->month;
                    $isSelected = $key === $selected;
                @endphp
                <button type="button" wire:click="selectDay('{{ $key }}')" wire:key="day-{{ $key }}"
                        class="flex flex-col items-center gap-1 py-1" aria-label="{{ $day->locale('id')->translatedFormat('j F Y') }}">
                    <span @class([
                        'flex h-8 w-8 items-center justify-center rounded-full',
                        'bg-accent font-bold text-white' => $isSelected,
                        'border-2 border-[var(--accent)] font-bold' => $day->isToday() && ! $isSelected,
                        'font-semibold' => $inMonth && ! $isSelected && ! $day->isToday(),
                        'text-muted-2' => ! $inMonth && ! $isSelected,
                    ])>{{ $day->day }}</span>
                    <span class="flex h-1 gap-0.5">
                        @foreach ($marks[$key] ?? [] as $mark)
                            <span class="{{ $mark }} h-1 w-1 rounded-full"></span>
                        @endforeach
                    </span>
                </button>
            @endfor
        </div>
    </div>

    <h2 class="mt-6 text-base font-bold">
        {{ $selectedDate->isToday() ? 'Hari Ini' : 'Agenda' }}
        ({{ $selectedDate->locale('id')->translatedFormat('l, j M') }})
    </h2>

    {{-- Form tambah: tidak ada frame-nya di Figma (frame kalender bahkan tidak punya tombol
         tambah). Dibangun dari komponen sistem yang sudah ada. --}}
    @if ($adding)
        <form wire:submit.prevent="addEvent" class="card mt-3 space-y-3 p-5">
            <div>
                <input type="text" wire:model="title" placeholder="Nama acara" aria-label="Nama acara"
                       class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink placeholder:text-muted-2 focus:outline-none focus:ring-2 focus:ring-[var(--accent)]">
                @error('title') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
            </div>

            <select wire:model="userId" aria-label="Penanggung jawab"
                    class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink">
                <option value="">Acara keluarga</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach
            </select>

            <div>
                <div class="flex gap-2">
                    <label class="min-w-0 flex-1 text-xs font-bold text-ink-soft">
                        Mulai
                        <input type="datetime-local" wire:model="startsAt"
                               class="mt-1 w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm font-normal text-ink">
                    </label>
                    <label class="min-w-0 flex-1 text-xs font-bold text-ink-soft">
                        Selesai
                        <input type="datetime-local" wire:model="endsAt"
                               class="mt-1 w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm font-normal text-ink">
                    </label>
                </div>
                @error('startsAt') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
                @error('endsAt') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2">
                <button type="button" wire:click="toggleForm"
                        class="bg-app flex-1 rounded-full border border-rule py-2.5 text-sm font-bold text-ink-soft">Batal</button>
                <button type="submit" class="bg-accent flex-1 rounded-full py-2.5 text-sm font-bold text-white">Simpan</button>
            </div>
        </form>
    @endif

    <div class="mt-3 space-y-3">
        @forelse ($agenda as $event)
            @php $tone = $event->user?->avatarColorClass() ?? 'bg-accent'; @endphp
            <div class="card flex gap-4 p-4" wire:key="event-{{ $event->id }}">
                <div class="w-14 shrink-0">
                    <p class="font-bold">{{ $event->starts_at->format('H:i') }}</p>
                    <p class="text-xs text-ink-soft">{{ $event->endLabel() }}</p>
                </div>
                <div class="{{ $tone }} w-1 shrink-0 rounded-full"></div>
                <div class="min-w-0 flex-1">
                    <p class="font-bold">{{ $event->title }}</p>
                    <p class="mt-1 flex items-center gap-2 text-xs text-ink-soft">
                        @if ($event->user)
                            <span class="{{ $tone }} flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white">
                                {{ mb_strtoupper(mb_substr($event->user->name, 0, 1)) }}
                            </span>
                            {{ $event->user->name }}
                        @else
                            Acara keluarga
                        @endif
                    </p>
                </div>
                <button type="button" wire:click="removeEvent({{ $event->id }})"
                        wire:confirm="Hapus acara &quot;{{ $event->title }}&quot;?"
                        class="shrink-0 self-start text-ink-soft" aria-label="Hapus {{ $event->title }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>
        @empty
            <p class="card p-6 text-center text-sm text-ink-soft">
                Tidak ada agenda di tanggal ini. Tekan tombol + untuk menambah.
            </p>
        @endforelse
    </div>

    <div class="h-20" aria-hidden="true"></div>

    <x-fab label="Tambah acara" wire:click="toggleForm" />
</div>
