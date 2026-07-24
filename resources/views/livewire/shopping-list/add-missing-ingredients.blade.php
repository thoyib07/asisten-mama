<div>
    @if ($added)
        <p class="text-xs font-medium text-green-700">✓ Bahan yang kurang sudah ditambahkan ke daftar belanja.</p>
    @else
        <button
            type="button"
            wire:click="add"
            class="text-xs font-semibold text-green-700 underline decoration-dotted"
        >
            + Tambahkan bahan yang kurang ke daftar belanja
        </button>
    @endif
</div>
