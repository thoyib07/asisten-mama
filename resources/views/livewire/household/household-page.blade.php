<div>
    <div class="card p-5 text-center">
        <div class="flex justify-center -space-x-3">
            @foreach ($members->take(5) as $member)
                <span class="{{ $member->avatarColorClass() }} flex h-11 w-11 items-center justify-center rounded-full border-2 border-white font-bold text-white">
                    {{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}
                </span>
            @endforeach
        </div>
        <h1 class="mt-4 text-xl font-extrabold">{{ $household->name }}</h1>
        <p class="text-sm text-ink-soft">Ruang koordinasi digital utama Anda.</p>

        @if ($role === 'owner')
            <a href="#undang" class="bg-accent mt-4 flex items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-bold text-white">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                Undang Anggota
            </a>
        @endif
    </div>

    @error('member')
        <p class="card border-danger text-danger mt-4 border p-4 text-sm">{{ $message }}</p>
    @enderror

    <h2 class="mt-6 text-base font-bold">Anggota Terdaftar</h2>

    <div class="mt-3 grid grid-cols-2 gap-3">
        @foreach ($members as $member)
            <div class="card relative flex items-center gap-3 p-4">
                <span class="{{ $member->avatarColorClass() }} flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white">
                    {{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold">{{ $member->name }}</p>
                    <p class="text-xs text-ink-soft">{{ $member->pivot->role === 'owner' ? 'Pemilik' : 'Anggota' }}</p>
                </div>
                @if ($role === 'owner' && $member->pivot->role !== 'owner')
                    <button
                        wire:click="removeMember({{ $member->id }})"
                        wire:confirm="Keluarkan {{ $member->name }} dari keluarga?"
                        class="absolute right-2 top-2 text-ink-soft"
                        aria-label="Keluarkan {{ $member->name }}"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                @elseif ($role !== 'owner' && $member->id === auth()->id())
                    <button
                        wire:click="removeMember({{ $member->id }})"
                        wire:confirm="Keluar dari keluarga ini?"
                        class="text-danger absolute right-2 top-2 text-[10px] font-bold"
                    >Keluar</button>
                @endif
            </div>
        @endforeach
    </div>

    <h2 class="mt-6 text-base font-bold">Preferensi &amp; Pengaturan</h2>

    {{-- ponytail: baris "Pengaturan Notifikasi" di frame sengaja tidak dirender — belum ada
         backend-nya, dan toggle yang tidak menyimpan apa pun lebih menyesatkan daripada absen.
         Tambahkan saat preferensi notifikasi punya tempat penyimpanan. --}}
    <div class="card mt-3 divide-y divide-[var(--rule)]">
        @if ($role === 'owner')
            <div id="undang" class="p-5">
                <p class="font-bold">Bagikan Tautan Undangan</p>
                <p class="text-xs text-ink-soft">Bagikan kode ini supaya anggota lain bisa gabung saat mendaftar.</p>
                <div class="mt-3 flex items-center gap-2">
                    <span class="bg-app flex-1 rounded-full border border-rule px-4 py-2.5 text-center font-mono text-lg tracking-widest">
                        {{ $household->invite_code }}
                    </span>
                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText('{{ $household->invite_code }}'); this.textContent='Disalin!';"
                        class="bg-accent shrink-0 rounded-full px-4 py-2.5 text-sm font-bold text-white"
                    >Salin</button>
                </div>
                <button
                    wire:click="regenerateInviteCode"
                    wire:confirm="Kode lama tidak akan berlaku lagi. Buat kode baru?"
                    class="mt-3 text-xs font-semibold text-ink-soft underline"
                >Buat kode baru</button>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3 p-5">
            <div class="min-w-0">
                <p class="truncate font-bold">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-ink-soft">{{ auth()->user()->email }}</p>
            </div>
            <button
                wire:click="logout"
                wire:confirm="Keluar dari akun?"
                class="text-danger shrink-0 text-sm font-bold"
            >Keluar</button>
        </div>
    </div>

    {{-- Integrasi budget_period_reset_day, lihat docs/prd/finance.md §6.11/Fase 8 --}}
</div>
