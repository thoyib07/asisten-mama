<div>
    <h1 class="text-2xl font-extrabold">Daftar Belanja</h1>
    <p class="text-sm text-ink-soft">Kelompokkan kebutuhan dapur &amp; rumah tangga.</p>

    {{-- ponytail: frame mengelompokkan item per ruangan (Dapur / Kamar Mandi), tapi
         shopping_list_items belum punya kolom kategori. Satu daftar dulu; tambahkan header grup
         saat kolomnya ada, jangan bikin kategori palsu di view. --}}
    <div class="mt-4 space-y-3">
        @forelse ($items as $item)
            <div class="card flex items-center gap-3 p-4">
                <button
                    type="button"
                    wire:click="toggleItem({{ $item->id }})"
                    @class([
                        'flex h-6 w-6 shrink-0 items-center justify-center rounded-md border-2',
                        'bg-accent border-transparent text-white' => $item->is_checked,
                        'border-rule' => ! $item->is_checked,
                    ])
                    aria-pressed="{{ $item->is_checked ? 'true' : 'false' }}"
                    aria-label="Tandai {{ $item->name }}"
                >
                    @if ($item->is_checked)
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4 10-10" /></svg>
                    @endif
                </button>

                <div class="min-w-0 flex-1">
                    <p @class(['truncate font-bold', 'text-ink-soft line-through' => $item->is_checked])>
                        {{ $item->name }}
                    </p>
                    @if ($item->addedBy)
                        <p class="mt-0.5 flex items-center gap-1.5 text-xs text-ink-soft">
                            <span class="{{ $item->addedBy->avatarColorClass() }} h-1.5 w-1.5 rounded-full"></span>
                            Ditambahkan oleh {{ $item->addedBy->name }}
                        </p>
                    @endif
                </div>

                @if ($item->quantity)
                    <span class="shrink-0 text-sm font-bold text-ink-soft">{{ $item->quantity }}</span>
                @endif

                <button
                    type="button"
                    wire:click="removeItem({{ $item->id }})"
                    class="shrink-0 text-ink-soft"
                    aria-label="Hapus {{ $item->name }}"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>
        @empty
            <p class="card p-6 text-center text-sm text-ink-soft">Daftar belanja masih kosong.</p>
        @endforelse
    </div>

    {{-- Bar sticky di atas nav. Frame menampilkan "Estimasi Total Budget Rp 150.000", tapi item
         belum punya kolom harga — menampilkan nominal karangan ke pengguna asli lebih buruk
         daripada menyimpang sedikit dari frame. Metriknya diganti ke angka yang benar-benar ada. --}}
    <div class="pointer-events-none fixed bottom-[76px] left-1/2 z-10 w-full max-w-[430px] -translate-x-1/2 px-6">
        <form wire:submit.prevent="addItem" class="card pointer-events-auto space-y-2 p-3">
            <p class="px-1 text-xs text-ink-soft">
                Belum dibeli
                <span class="text-accent font-extrabold">{{ $items->where('is_checked', false)->count() }} item</span>
            </p>
            <div class="flex gap-2">
                <input
                    type="text"
                    wire:model="newItem"
                    placeholder="Tambah barang..."
                    aria-label="Nama barang"
                    class="min-w-0 flex-1 rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink placeholder:text-muted-2 focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
                >
                <button type="submit" class="bg-accent shrink-0 rounded-full px-5 py-2.5 text-sm font-bold text-white">
                    + Item
                </button>
            </div>
        </form>
    </div>

    {{-- Ruang supaya item terakhir tidak tertutup bar sticky. --}}
    <div class="h-32" aria-hidden="true"></div>
</div>
