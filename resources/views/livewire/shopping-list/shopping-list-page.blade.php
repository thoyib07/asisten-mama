<div class="space-y-4">
    <div>
        <h1 class="text-xl font-bold text-stone-800">Daftar Belanja</h1>
        <p class="text-sm text-stone-400">Catat apa saja yang perlu dibeli untuk keluarga.</p>
    </div>

    <form wire:submit.prevent="addItem" class="flex gap-2">
        <input
            type="text"
            wire:model="newItem"
            placeholder="Tambah barang... (misal: beras)"
            aria-label="Nama barang"
            class="flex-1 rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-green-600 placeholder-stone-400"
        >
        <button type="submit" class="rounded-xl bg-green-700 px-4 py-3 text-sm font-bold text-white active:bg-green-800">
            Tambah
        </button>
    </form>

    <section class="space-y-2">
        @forelse ($items as $item)
            <div class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm">
                <input
                    type="checkbox"
                    wire:click="toggleItem({{ $item->id }})"
                    @checked($item->is_checked)
                    class="h-5 w-5 rounded border-stone-300 text-green-700 focus:ring-green-600"
                >
                <span class="flex-1 text-sm {{ $item->is_checked ? 'text-stone-400 line-through' : 'text-stone-800' }}">
                    {{ $item->name }}
                    @if ($item->quantity)
                        <span class="text-xs text-stone-400">({{ $item->quantity }})</span>
                    @endif
                </span>
                <button wire:click="removeItem({{ $item->id }})" aria-label="Hapus" class="text-stone-300 hover:text-red-500">&times;</button>
            </div>
        @empty
            <p class="rounded-xl bg-white p-4 text-center text-sm text-stone-400 shadow-sm">
                Daftar belanja masih kosong.
            </p>
        @endforelse
    </section>
</div>
