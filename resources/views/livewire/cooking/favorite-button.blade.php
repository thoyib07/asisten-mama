{{-- Bintang SVG, bukan emoji: tombol ini dipakai di tiga latar berbeda — di atas foto resep
     (grid), di atas kartu putih (hasil pencarian bahan), dan di atas latar halaman (detail).
     Bentuk outline + drop-shadow supaya tetap kelihatan di ketiganya. --}}
<button
    type="button"
    wire:click.stop.prevent="toggle"
    aria-label="{{ $isFavorited ? 'Hapus dari favorit' : 'Tambah ke favorit' }}"
    aria-pressed="{{ $isFavorited ? 'true' : 'false' }}"
    class="shrink-0 leading-none drop-shadow {{ $isFavorited ? 'text-amber-500' : 'text-ink-soft' }}"
>
    <svg class="h-6 w-6" viewBox="0 0 24 24"
         fill="{{ $isFavorited ? 'currentColor' : 'white' }}"
         stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true">
        <path d="m12 3.5 2.7 5.6 6.1.9-4.4 4.3 1 6.2-5.4-2.9-5.4 2.9 1-6.2L3.2 10l6.1-.9z" />
    </svg>
</button>
