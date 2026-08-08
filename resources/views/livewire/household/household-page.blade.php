<div class="space-y-5">
    <div>
        <h1 class="text-xl font-bold text-ink">Keluarga</h1>
        <p class="text-sm text-ink-soft">{{ $household->name }}</p>
    </div>

    @error('member')
        <p class="rounded-lg border border-stamp bg-surface px-4 py-2 text-sm text-stamp">{{ $message }}</p>
    @enderror

    <section class="space-y-2">
        <h2 class="text-sm font-semibold text-ink-soft">Anggota rumah tangga</h2>
        <div class="space-y-2">
            @foreach ($members as $member)
                <div class="flex items-center gap-3 rounded-lg border border-rule bg-surface p-3">
                    <div class="{{ $member->avatarColorClass() }} flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white">
                        {{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-ink">{{ $member->name }}</p>
                        <p class="text-xs text-ink-soft">{{ $member->pivot->role === 'owner' ? 'Pemilik' : 'Anggota' }}</p>
                    </div>
                    @if ($role === 'owner' && $member->pivot->role !== 'owner')
                        <button wire:click="removeMember({{ $member->id }})" wire:confirm="Keluarkan {{ $member->name }} dari keluarga?" class="text-xs font-medium text-stamp">
                            Hapus
                        </button>
                    @elseif ($role !== 'owner' && $member->id === auth()->id())
                        <button wire:click="removeMember({{ $member->id }})" wire:confirm="Keluar dari keluarga ini?" class="text-xs font-medium text-stamp">
                            Keluar
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    @if ($role === 'owner')
        <section class="space-y-2">
            <h2 class="text-sm font-semibold text-ink-soft">Undang anggota</h2>
            <div class="rounded-lg border border-rule bg-surface p-3 space-y-3">
                <p class="text-sm text-ink-soft">Bagikan kode ini supaya anggota lain bisa gabung saat mendaftar.</p>
                <div class="flex items-center gap-2">
                    <span class="flex-1 rounded-md border border-rule bg-app px-3 py-2 text-center font-mono text-lg tracking-widest text-ink">
                        {{ $household->invite_code }}
                    </span>
                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText('{{ $household->invite_code }}'); this.textContent='Disalin!';"
                        class="rounded-md bg-accent px-3 py-2 text-sm font-semibold text-white"
                    >
                        Salin
                    </button>
                </div>
                <button wire:click="regenerateInviteCode" wire:confirm="Kode lama tidak akan berlaku lagi. Buat kode baru?" class="text-xs font-medium text-ink-soft underline">
                    Buat kode baru
                </button>
            </div>
        </section>
    @endif

    <section class="space-y-2">
        <h2 class="text-sm font-semibold text-ink-soft">Akun</h2>
        <div class="rounded-lg border border-rule bg-surface p-3 space-y-3">
            <div>
                <p class="text-sm font-medium text-ink">{{ auth()->user()->name }}</p>
                <p class="text-xs text-ink-soft">{{ auth()->user()->email }}</p>
            </div>
            <button wire:click="logout" wire:confirm="Keluar dari akun?" class="text-sm font-medium text-stamp">
                Keluar
            </button>
        </div>
    </section>

    {{-- Integrasi budget_period_reset_day, lihat docs/prd/finance.md §6.11/Fase 8 --}}
</div>
