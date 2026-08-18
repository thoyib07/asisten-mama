{{-- Brand bar per-halaman (docs/ui-design.md §5.1). Hanya dipakai di Beranda & Buku Resep,
     sesuai frame — halaman lain langsung mulai dengan judulnya. Slot untuk ikon aksi kanan. --}}
<div class="flex items-center justify-between">
    <span class="flex items-center gap-2">
        <span class="bg-accent flex h-8 w-8 items-center justify-center rounded-xl text-white">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 10.5 12 3l9 7.5M5.25 9.75V21h13.5V9.75" />
            </svg>
        </span>
        <span class="text-accent text-lg font-extrabold">Keluarga</span>
    </span>
    {{ $slot }}
</div>
