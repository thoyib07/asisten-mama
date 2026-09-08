<div>
    @php
        $tiles = [
            ['label' => 'Kalender', 'tone' => 'tile-kalender', 'href' => route('kalender'), 'count' => $todayEventCount,
             'icon' => 'M4 6.75A1.75 1.75 0 0 1 5.75 5h12.5A1.75 1.75 0 0 1 20 6.75v12.5A1.75 1.75 0 0 1 18.25 21H5.75A1.75 1.75 0 0 1 4 19.25zM4 10h16M8 3v4M16 3v4'],
            ['label' => 'Tugas', 'tone' => 'tile-tugas', 'href' => route('tugas'), 'count' => $pendingTaskCount,
             'icon' => 'M8 6H6.75A1.75 1.75 0 0 0 5 7.75v11.5A1.75 1.75 0 0 0 6.75 21h10.5A1.75 1.75 0 0 0 19 19.25V7.75A1.75 1.75 0 0 0 17.25 6H16M8 6V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v1zM9 13l2 2 4-4'],
            ['label' => 'Belanja', 'tone' => 'tile-belanja', 'href' => route('shopping-list.index'), 'count' => $pendingShoppingCount,
             'icon' => 'M3 4h2l2.4 11.2a1.5 1.5 0 0 0 1.5 1.2h8.2a1.5 1.5 0 0 0 1.5-1.2L21 8H6M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2M18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2'],
            ['label' => 'Resep', 'tone' => 'tile-resep', 'href' => route('recipes.index'), 'count' => $newRecipeCount,
             'icon' => 'M7 21h10M6 8a3 3 0 0 1 3-3 3 3 0 0 1 6 0 3 3 0 0 1 3 3 3 3 0 0 1-2 2.83V17H8v-6.17A3 3 0 0 1 6 8'],
            ['label' => 'Tagihan', 'tone' => 'tile-tagihan', 'href' => route('tagihan'), 'count' => $dueBillCount,
             'icon' => 'M3 8.75A1.75 1.75 0 0 1 4.75 7h14.5A1.75 1.75 0 0 1 21 8.75v6.5A1.75 1.75 0 0 1 19.25 17H4.75A1.75 1.75 0 0 1 3 15.25zM3 11h18M6.5 14h3'],
        ];
    @endphp

    {{-- "Keluarga" ditujukan ke orang yang sudah memakai aplikasinya; pengunjung pertama kali
         belum tahu ini aplikasi apa, jadi mereka melihat nama produknya. --}}
    <x-brand-bar :name="auth()->check() ? 'Keluarga' : 'Asisten Mama'">
        <button type="button" class="text-ink-soft" aria-label="Notifikasi">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7.5-2 7.5h16s-2-1.5-2-7.5M10.3 19.5a2 2 0 0 0 3.4 0" />
            </svg>
        </button>
    </x-brand-bar>

    <header class="mt-5">
        <h1 class="text-2xl font-extrabold">{{ $greeting }}</h1>
        <p class="text-sm text-ink-soft">Mari selaraskan hari ini bersama.</p>
    </header>

    @if ($members->isNotEmpty())
        <div class="mt-4 flex items-center gap-2">
            @foreach ($members as $member)
                <span
                    class="{{ $member->avatarColorClass() }} flex h-10 w-10 items-center justify-center rounded-full font-bold text-white"
                    title="{{ $member->name }}"
                >{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
            @endforeach
            <a
                wire:navigate
                href="{{ route('household.index') }}"
                class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-dashed border-rule text-ink-soft"
                aria-label="Undang anggota"
            >+</a>
        </div>
    @endif

    <div class="mt-5 grid grid-cols-3 gap-3">
        @foreach ($tiles as $tile)
            <x-tile
                :label="$tile['label']"
                :icon="$tile['icon']"
                :tone="$tile['tone']"
                :href="$tile['href'] ?? null"
                :count="$tile['count'] ?? 0"
            />
        @endforeach
    </div>

    {{-- Kartu Acara Terdekat & Tugas Hari Ini mengandaikan ada keluarga di baliknya: untuk tamu
         keduanya cuma akan berbunyi "semua tugas beres", yang menyesatkan karena tugasnya memang
         belum pernah ada. Diganti satu keadaan kosong yang jujur + ajakan masuk. --}}
    @guest
        <div class="card mt-6 p-6 text-center">
            <p class="font-bold">Belum ada apa-apa di sini.</p>
            <p class="text-ink-soft mt-1 text-sm leading-relaxed">
                Masuk untuk mulai memakainya — acara, tugas, belanja, dan catatan keuangan
                keluarga muncul di halaman ini.
            </p>
            <a wire:navigate href="{{ route('recipes.index') }}"
               class="bg-tint text-accent mt-4 inline-block rounded-full px-5 py-2.5 text-sm font-bold">
                Lihat resep dulu, tanpa daftar
            </a>
        </div>
    @else

    <div class="mt-6 flex items-center justify-between">
        <h2 class="text-base font-bold">Acara Terdekat</h2>
        <a wire:navigate href="{{ route('kalender') }}" class="text-accent text-sm font-semibold">Lihat Semua</a>
    </div>

    @if ($nextEvent)
        <a wire:navigate href="{{ route('kalender') }}" class="card mt-3 block p-5">
            <div class="flex items-start justify-between">
                <span class="bg-tint text-accent flex h-12 w-12 flex-col items-center justify-center rounded-2xl leading-none">
                    <span class="text-base font-extrabold">{{ $nextEvent->starts_at->format('j') }}</span>
                    <span class="text-[10px] font-bold">{{ mb_strtoupper($nextEvent->starts_at->locale('id')->translatedFormat('M')) }}</span>
                </span>
                {{-- ponytail: frame menumpuk avatar semua peserta, tapi acara baru punya satu
                     penanggung jawab. Tumpukan peserta menunggu tabel pivot event_user. --}}
                @if ($nextEvent->user)
                    <span class="{{ $nextEvent->user->avatarColorClass() }} flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold text-white">
                        {{ mb_strtoupper(mb_substr($nextEvent->user->name, 0, 1)) }}
                    </span>
                @endif
            </div>
            <p class="mt-4 font-bold">{{ $nextEvent->title }}</p>
            <p class="mt-1 text-xs text-ink-soft">
                🕘 {{ $nextEvent->starts_at->format('H:i') }} - {{ $nextEvent->endLabel() }}
            </p>
        </a>
    @else
        <a wire:navigate href="{{ route('kalender') }}" class="card mt-3 block p-5 text-sm text-ink-soft">
            Belum ada acara terjadwal.
        </a>
    @endif

    <h2 class="mt-6 text-base font-bold">Tugas Hari Ini</h2>

    <a wire:navigate href="{{ route('tugas') }}" class="card mt-3 flex items-center gap-4 p-5">
        <span class="bg-tint text-accent flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M8 6H6.75A1.75 1.75 0 0 0 5 7.75v11.5A1.75 1.75 0 0 0 6.75 21h10.5A1.75 1.75 0 0 0 19 19.25V7.75A1.75 1.75 0 0 0 17.25 6H16M8 6V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v1zM9 13l2 2 4-4" />
            </svg>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block font-bold">
                {{ $pendingTaskCount > 0 ? $pendingTaskCount.' Tugas Menunggu' : 'Semua tugas beres' }}
            </span>
            <span class="mt-0.5 block truncate text-xs text-ink-soft">
                @if ($nextTask)
                    {{ $nextTask->user?->name ?? 'Belum ditugaskan' }} — "{{ $nextTask->title }}"
                @else
                    Tidak ada tugas yang menunggu.
                @endif
            </span>
            <span class="mt-2 block h-1.5 w-full rounded-full bg-rule">
                <span class="bg-accent block h-1.5 rounded-full" style="width: {{ $taskProgress }}%"></span>
            </span>
        </span>
    </a>
    @endguest
</div>
