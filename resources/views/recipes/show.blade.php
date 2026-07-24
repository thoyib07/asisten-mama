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
                        <span class="text-sm leading-relaxed text-stone-700">{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Rating --}}
        @auth
            <section class="rounded-xl bg-white p-4 shadow-sm">
                @livewire('cooking::recipe-rating', ['recipe' => $recipe])
            </section>
        @endauth
    </article>
</x-layout>
