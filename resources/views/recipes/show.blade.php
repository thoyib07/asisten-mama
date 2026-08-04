<x-layout>
    <article class="space-y-5">
        {{-- Tombol kembali --}}
        <button
            type="button"
            onclick="window.history.back()"
            class="inline-flex items-center gap-1 text-sm font-semibold text-green-700"
        >
            ← Kembali
        </button>

        {{-- Gambar --}}
        <div class="overflow-hidden rounded-2xl bg-amber-100 h-48 flex items-center justify-center">
            @if ($recipe->image_url)
                <img src="{{ $recipe->image_url }}" alt="{{ $recipe->name }}" class="w-full h-full object-cover">
            @else
                <span class="text-6xl">🍳</span>
            @endif
        </div>

        {{-- Judul + meta --}}
        <div class="flex items-start justify-between gap-2">
            <div>
                <h1 class="text-2xl font-bold text-stone-800">{{ $recipe->name }}</h1>
                @if ($recipe->servings)
                    <span class="mt-1 inline-block rounded-full bg-stone-100 px-3 py-0.5 text-xs text-stone-500">
                        🍽️ {{ $recipe->servings }} porsi
                    </span>
                @endif
                @if ($recipe->duration_minutes)
                    <span class="mt-1 inline-block rounded-full bg-stone-100 px-3 py-0.5 text-xs text-stone-500">
                        ⏱️ {{ $recipe->duration_minutes }} menit
                    </span>
                @endif
                @if ($recipe->source === 'ai')
                    <p class="mt-1 text-xs text-orange-500">✨ Resep dari AI — cek kematangan &amp; kebersihan sendiri.</p>
                @endif
            </div>
            @auth
                @livewire('cooking::favorite-button', ['recipeId' => $recipe->id])
            @endauth
        </div>

        {{-- Bahan --}}
        <section>
            <h2 class="mb-2 font-bold text-stone-700">Bahan</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($recipe->ingredients as $ingredient)
                    <span class="rounded-lg bg-stone-100 px-3 py-1 text-sm text-stone-700">
                        {{ $ingredient->name }}
                        @if ($ingredient->pivot->quantity)
                            <span class="text-stone-400">— {{ $ingredient->pivot->quantity }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </section>

        {{-- Langkah --}}
        <section>
            <h2 class="mb-3 font-bold text-stone-700">Langkah Memasak</h2>
            <ol class="space-y-3">
                @foreach ($recipe->steps as $index => $step)
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-700 text-xs font-bold text-white">
                            {{ $index + 1 }}
                        </span>
                        <span class="text-sm leading-relaxed text-stone-700">
                            {{ $step['text'] }}
                            @if ($step['duration_minutes'])
                                <span class="ml-1 inline-block rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-500">~{{ $step['duration_minutes'] }} menit</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Nilai gizi (perkiraan) --}}
        @if (collect($recipe->nutrition ?? [])->filter(fn ($v) => $v !== null)->isNotEmpty())
            <section>
                <h2 class="mb-2 font-bold text-stone-700">Perkiraan Nilai Gizi</h2>
                <p class="mb-2 text-xs text-stone-400">Estimasi kasar untuk keseluruhan resep, bukan hasil hitungan presisi.</p>
                <div class="grid grid-cols-4 gap-2">
                    <div class="rounded-lg bg-stone-100 py-2 text-center">
                        <span class="block text-sm font-bold text-stone-700">{{ $recipe->nutrition['calories'] ?? '–' }}</span>
                        <span class="block text-xs text-stone-400">kkal</span>
                    </div>
                    <div class="rounded-lg bg-stone-100 py-2 text-center">
                        <span class="block text-sm font-bold text-stone-700">{{ $recipe->nutrition['protein'] ?? '–' }}</span>
                        <span class="block text-xs text-stone-400">protein (g)</span>
                    </div>
                    <div class="rounded-lg bg-stone-100 py-2 text-center">
                        <span class="block text-sm font-bold text-stone-700">{{ $recipe->nutrition['carbs'] ?? '–' }}</span>
                        <span class="block text-xs text-stone-400">karbo (g)</span>
                    </div>
                    <div class="rounded-lg bg-stone-100 py-2 text-center">
                        <span class="block text-sm font-bold text-stone-700">{{ $recipe->nutrition['fat'] ?? '–' }}</span>
                        <span class="block text-xs text-stone-400">lemak (g)</span>
                    </div>
                </div>
            </section>
        @endif

        {{-- Rating --}}
        @auth
            <section class="rounded-xl bg-white p-4 shadow-sm">
                @livewire('cooking::recipe-rating', ['recipe' => $recipe])
            </section>
        @endauth
    </article>
</x-layout>
