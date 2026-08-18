{{-- Tombol bulat mengambang kanan bawah (docs/ui-design.md §5.3). Dibungkus wrapper selebar
     kontainer utama supaya posisinya ikut kolom konten, bukan tepi layar. --}}
@props(['label' => 'Tambah'])

<div class="pointer-events-none fixed bottom-24 left-1/2 z-10 flex w-full max-w-[430px] -translate-x-1/2 justify-end px-6">
    <button
        type="button"
        {{ $attributes->merge(['class' => 'bg-accent pointer-events-auto flex h-14 w-14 items-center justify-center rounded-full text-white shadow-lg']) }}
        aria-label="{{ $label }}"
    >
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
             stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
    </button>
</div>
