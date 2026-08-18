<div>
    <p class="mb-2 text-sm font-bold text-ink-soft">{{ $hasRated ? 'Ubah rating kamu:' : 'Nilai resep ini:' }}</p>
    <div class="flex gap-1" role="group" aria-label="Rating bintang">
        @for ($i = 1; $i <= 5; $i++)
            <button
                wire:click="rate({{ $i }})"
                aria-label="Beri {{ $i }} bintang"
                class="text-2xl leading-none transition-transform active:scale-110
                    {{ $i <= round($average) ? 'text-amber-500' : 'text-ink-soft' }}"
            >★</button>
        @endfor
    </div>
    <p class="mt-1 text-xs text-ink-soft">{{ $average }} / 5 &bull; {{ $count }} penilaian</p>
    @if ($hasRated)
        <p class="text-accent mt-2 text-sm font-bold">✓ Terima kasih atas penilaiannya! Klik bintang lagi untuk mengubah.</p>
    @endif
</div>
