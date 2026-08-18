<div>
    @if ($added)
        <p class="text-accent text-xs font-bold">✓ Bahan yang kurang sudah ditambahkan ke daftar belanja.</p>
    @else
        <button
            type="button"
            wire:click="add"
            class="text-accent text-xs font-bold underline decoration-dotted"
        >
            + Tambahkan bahan yang kurang ke daftar belanja
        </button>
    @endif
</div>
