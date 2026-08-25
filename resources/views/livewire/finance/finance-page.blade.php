<div>
    <h1 class="text-2xl font-extrabold">Keuangan Keluarga</h1>
    <p class="text-sm text-ink-soft">Catat pemasukan dan pengeluaran keluarga.</p>

    {{-- Filter periode. Ringkasan & riwayat di bawah selalu mengikuti pilihan di sini. --}}
    <div class="mt-4 flex items-center gap-2">
        <label class="min-w-0 flex-1">
            <select wire:model.live="periodStart" aria-label="Periode"
                    class="w-full rounded-full border border-rule bg-surface px-4 py-2.5 text-sm font-bold text-ink">
                @foreach ($periods as $period)
                    <option value="{{ $period['value'] }}">{{ $period['title'] }}</option>
                @endforeach
            </select>
        </label>
        <a wire:navigate href="{{ route('finance.kantong') }}"
           class="shrink-0 rounded-full bg-tint px-4 py-2.5 text-sm font-bold text-accent">Kantong</a>
    </div>
    @if ($periodLabel['range'])
        <p class="text-ink-soft mt-1 text-xs">{{ $periodLabel['range'] }}</p>
    @endif

    <section class="mt-3 grid grid-cols-3 gap-2">
        <div class="card-sm p-3 text-center">
            <p class="text-ink-soft text-xs">Pemasukan</p>
            <p class="text-accent text-sm font-bold">Rp{{ number_format($monthlyIncome, 0, ',', '.') }}</p>
        </div>
        <div class="card-sm p-3 text-center">
            <p class="text-ink-soft text-xs">Pengeluaran</p>
            <p class="text-danger text-sm font-bold">Rp{{ number_format($monthlyExpense, 0, ',', '.') }}</p>
        </div>
        <div class="card-sm p-3 text-center">
            <p class="text-ink-soft text-xs">Saldo</p>
            <p class="text-sm font-bold {{ $monthlyBalance >= 0 ? 'text-accent' : 'text-danger' }}">Rp{{ number_format($monthlyBalance, 0, ',', '.') }}</p>
        </div>
    </section>

    <form wire:submit.prevent="save" class="card mt-4 space-y-3 p-5">
        <div class="flex gap-2">
            <button type="button" wire:click="$set('type', 'expense')"
                @class(['flex-1 rounded-full py-2 text-sm font-bold', 'bg-accent text-white' => $type === 'expense', 'bg-app text-ink-soft border border-rule' => $type !== 'expense'])>
                Pengeluaran
            </button>
            <button type="button" wire:click="$set('type', 'income')"
                @class(['flex-1 rounded-full py-2 text-sm font-bold', 'bg-accent text-white' => $type === 'income', 'bg-app text-ink-soft border border-rule' => $type !== 'income'])>
                Pemasukan
            </button>
        </div>

        <div>
            <select wire:model="categoryId" aria-label="{{ $type === 'expense' ? 'Kantong' : 'Kategori' }}"
                    class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink">
                <option value="">{{ $type === 'expense' ? 'Pilih kantong' : 'Pilih kategori (opsional)' }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">
                        {{ $category->icon }} {{ $category->name }}@if ($type === 'expense' && ($remaining[$category->id] ?? null) !== null) — sisa Rp{{ number_format($remaining[$category->id], 0, ',', '.') }}@endif
                    </option>
                @endforeach
            </select>
            @error('categoryId') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
        </div>

        <div>
            <input type="number" step="0.01" wire:model="amount" placeholder="Jumlah (Rp)" aria-label="Jumlah"
                   class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink placeholder:text-muted-2">
            @error('amount') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
        </div>

        <input type="text" wire:model="description" placeholder="Catatan (opsional)" aria-label="Catatan"
               class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink placeholder:text-muted-2">

        <div>
            <input type="date" wire:model="occurredOn" aria-label="Tanggal"
                   class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink">
            @error('occurredOn') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-accent flex-1 rounded-full py-3 font-bold text-white">
                {{ $editingId ? 'Simpan perubahan' : 'Simpan' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="cancelEdit"
                        class="bg-app rounded-full border border-rule px-5 py-3 text-sm font-bold text-ink-soft">Batal</button>
            @endif
        </div>
    </form>

    <section class="mt-4 space-y-2">
        @forelse ($transactions as $t)
            <div class="card-sm flex items-center justify-between gap-3 p-4">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold">{{ $t->category?->icon }} {{ $t->category?->name ?? 'Tanpa kategori' }}</p>
                    <p class="text-ink-soft text-xs">{{ $t->occurred_on->locale('id')->isoFormat('D MMM Y') }}@if ($t->description) &bull; {{ $t->description }} @endif</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="text-sm font-bold {{ $t->type === 'income' ? 'text-accent' : 'text-danger' }}">
                        {{ $t->type === 'income' ? '+' : '-' }}Rp{{ number_format($t->amount, 0, ',', '.') }}
                    </span>
                    <button type="button" wire:click="edit({{ $t->id }})" aria-label="Ubah transaksi"
                            class="text-ink-soft text-xs font-bold">Ubah</button>
                    <button type="button" wire:click="delete({{ $t->id }})" wire:confirm="Hapus transaksi ini?"
                            aria-label="Hapus transaksi" class="text-danger text-xs font-bold">Hapus</button>
                </div>
            </div>
        @empty
            <p class="card-sm text-ink-soft p-4 text-center text-sm">Belum ada catatan transaksi di periode ini.</p>
        @endforelse
    </section>
</div>
