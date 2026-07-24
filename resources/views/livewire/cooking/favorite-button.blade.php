<button
    type="button"
    wire:click.stop.prevent="toggle"
    aria-label="{{ $isFavorited ? 'Hapus dari favorit' : 'Tambah ke favorit' }}"
    aria-pressed="{{ $isFavorited ? 'true' : 'false' }}"
    class="shrink-0 text-xl leading-none {{ $isFavorited ? 'text-amber-500' : 'text-stone-300' }}"
>
    {{ $isFavorited ? '⭐' : '☆' }}
</button>
