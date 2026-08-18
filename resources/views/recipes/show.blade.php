<x-layout>
    <article class="space-y-5">
        {{-- Tombol kembali --}}
        <button
            type="button"
            onclick="window.history.back()"
            class="text-accent inline-flex items-center gap-1 text-sm font-bold"
        >
            ← Kembali
        </button>

        {{-- Gambar --}}
        <div class="bg-tint flex h-48 items-center justify-center overflow-hidden rounded-3xl">
            @if ($recipe->image_url)
                <img src="{{ $recipe->image_url }}" alt="{{ $recipe->name }}" class="w-full h-full object-cover">
            @else
                <span class="text-6xl">🍳</span>
            @endif
        </div>

        {{-- Judul + meta --}}
        <div class="flex items-start justify-between gap-2">
            <div>
                <h1 class="text-2xl font-extrabold">{{ $recipe->name }}</h1>
                @if ($recipe->servings)
                    <span class="badge badge-muted mt-1 inline-block">
                        🍽️ {{ $recipe->servings }} porsi
                    </span>
                @endif
                @if ($recipe->duration_minutes)
                    <span class="badge badge-muted mt-1 inline-block">
                        ⏱️ {{ $recipe->duration_minutes }} menit
                    </span>
                @endif
                @if ($recipe->source === 'ai')
                    <p class="mt-1 text-xs text-ink-soft">✨ Resep dari AI — cek kematangan &amp; kebersihan sendiri.</p>
                @endif
            </div>
            @auth
                @livewire('cooking::favorite-button', ['recipeId' => $recipe->id])
            @endauth
        </div>

        {{-- Bahan --}}
        <section>
            <h2 class="mb-2 text-base font-bold">Bahan</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($recipe->ingredients as $ingredient)
                    <span class="bg-surface rounded-full border border-rule px-3 py-1.5 text-sm">
                        {{ $ingredient->name }}
                        @if ($ingredient->pivot->quantity)
                            <span class="text-ink-soft">— {{ $ingredient->pivot->quantity }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </section>

        {{-- Langkah --}}
        <section>
            <h2 class="mb-3 text-base font-bold">Langkah Memasak</h2>
            <ol class="space-y-3">
                @foreach ($recipe->steps as $index => $step)
                    <li class="flex items-start gap-3">
                        <span class="bg-accent mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white">
                            {{ $index + 1 }}
                        </span>
                        <span class="text-sm leading-relaxed">
                            {{ $step['text'] }}
                            @if ($step['duration_minutes'])
                                <span class="badge badge-muted ml-1 inline-block">~{{ $step['duration_minutes'] }} menit</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Nilai gizi (perkiraan) --}}
        @if (collect($recipe->nutrition ?? [])->filter(fn ($v) => $v !== null)->isNotEmpty())
            <section>
                <h2 class="mb-2 text-base font-bold">Perkiraan Nilai Gizi</h2>
                <p class="mb-2 text-xs text-ink-soft">Estimasi kasar untuk keseluruhan resep, bukan hasil hitungan presisi.</p>
                <div class="grid grid-cols-4 gap-2">
                    <div class="card-sm py-2 text-center">
                        <span class="block text-sm font-bold">{{ $recipe->nutrition['calories'] ?? '–' }}</span>
                        <span class="block text-xs text-ink-soft">kkal</span>
                    </div>
                    <div class="card-sm py-2 text-center">
                        <span class="block text-sm font-bold">{{ $recipe->nutrition['protein'] ?? '–' }}</span>
                        <span class="block text-xs text-ink-soft">protein (g)</span>
                    </div>
                    <div class="card-sm py-2 text-center">
                        <span class="block text-sm font-bold">{{ $recipe->nutrition['carbs'] ?? '–' }}</span>
                        <span class="block text-xs text-ink-soft">karbo (g)</span>
                    </div>
                    <div class="card-sm py-2 text-center">
                        <span class="block text-sm font-bold">{{ $recipe->nutrition['fat'] ?? '–' }}</span>
                        <span class="block text-xs text-ink-soft">lemak (g)</span>
                    </div>
                </div>
            </section>
        @endif

        {{-- Rating --}}
        @auth
            <section class="card p-5">
                @livewire('cooking::recipe-rating', ['recipe' => $recipe])
            </section>
        @endauth
    </article>
</x-layout>
