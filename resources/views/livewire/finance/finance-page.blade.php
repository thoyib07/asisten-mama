<div class="space-y-4">
    <div>
        <h1 class="text-xl font-bold text-stone-800">Keuangan Keluarga</h1>
        <p class="text-sm text-stone-400">Catat pemasukan dan pengeluaran keluarga.</p>
    </div>

    {{-- Ringkasan bulan ini --}}
    <section class="grid grid-cols-3 gap-2">
        <div class="rounded-xl bg-white p-3 text-center shadow-sm">
            <p class="text-xs text-stone-400">Pemasukan</p>
            <p class="font-bold text-green-700">Rp{{ number_format($monthlyIncome, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-white p-3 text-center shadow-sm">
            <p class="text-xs text-stone-400">Pengeluaran</p>
            <p class="font-bold text-red-600">Rp{{ number_format($monthlyExpense, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-white p-3 text-center shadow-sm">
            <p class="text-xs text-stone-400">Saldo</p>
            <p class="font-bold {{ $monthlyBalance >= 0 ? 'text-green-700' : 'text-red-600' }}">Rp{{ number_format($monthlyBalance, 0, ',', '.') }}</p>
        </div>
    </section>

    {{-- Form input --}}
    <form wire:submit.prevent="save" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        <div class="flex gap-2">
            <button type="button" wire:click="$set('type', 'expense')"
                class="flex-1 rounded-lg py-2 text-sm font-semibold {{ $type === 'expense' ? 'bg-red-100 text-red-700' : 'bg-stone-100 text-stone-500' }}">
                Pengeluaran
            </button>
            <button type="button" wire:click="$set('type', 'income')"
                class="flex-1 rounded-lg py-2 text-sm font-semibold {{ $type === 'income' ? 'bg-green-100 text-green-700' : 'bg-stone-100 text-stone-500' }}">
                Pemasukan
            </button>
        </div>

        <select wire:model="categoryId" class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm">
            <option value="">Pilih kategori (opsional)</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->icon }} {{ $category->name }}</option>
            @endforeach
        </select>

        <input type="number" step="0.01" wire:model="amount" placeholder="Jumlah (Rp)"
            class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm">
        @error('amount') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

        <input type="text" wire:model="description" placeholder="Catatan (opsional)"
            class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm">

        <input type="date" wire:model="occurredOn"
            class="w-full rounded-lg border border-stone-200 px-3 py-2 text-sm">

        <button type="submit" class="w-full rounded-xl bg-green-700 py-3 font-bold text-white active:bg-green-800">
            Simpan
        </button>
    </form>

    {{-- Riwayat --}}
    <section class="space-y-2">
        @forelse ($transactions as $t)
            <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-sm">
                <div>
                    <p class="text-sm font-medium text-stone-800">{{ $t->category?->icon }} {{ $t->category?->name ?? 'Tanpa kategori' }}</p>
                    <p class="text-xs text-stone-400">{{ $t->occurred_on->format('d M Y') }} @if($t->description) &bull; {{ $t->description }} @endif</p>
                </div>
                <span class="font-bold {{ $t->type === 'income' ? 'text-green-700' : 'text-red-600' }}">
                    {{ $t->type === 'income' ? '+' : '-' }}Rp{{ number_format($t->amount, 0, ',', '.') }}
                </span>
            </div>
        @empty
            <p class="rounded-xl bg-white p-4 text-center text-sm text-stone-400 shadow-sm">
                Belum ada catatan transaksi.
            </p>
        @endforelse
    </section>
</div>
