<div>
    <p class="mb-2 text-sm font-semibold text-stone-600">Nilai resep ini:</p>
    <div class="flex gap-1" role="group" aria-label="Rating bintang">
        @for ($i = 1; $i <= 5; $i++)
            <button
                wire:click="rate({{ $i }})"
                @disabled($hasRated)
                aria-label="Beri {{ $i }} bintang"
                class="text-2xl leading-none transition-transform active:scale-110 disabled:cursor-default
                    {{ $i <= round($average) ? 'text-amber-400' : 'text-stone-300' }}"
            >★</button>
        @endfor
    </div>
    <p class="mt-1 text-xs text-stone-400">{{ $average }} / 5 &bull; {{ $count }} penilaian</p>
    @if ($hasRated)
        <p class="mt-2 text-sm font-medium text-green-700">✓ Terima kasih atas penilaiannya!</p>
    @endif
</div>
