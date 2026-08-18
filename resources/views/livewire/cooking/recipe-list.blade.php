<div>
    @unless ($lockFavoritesFilter)
        <x-brand-bar>
            <a wire:navigate href="{{ route('favorites.index') }}" class="text-ink-soft" aria-label="Resep favorit">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 20s-7-4.35-7-9a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 4.65-7 9-7 9" />
                </svg>
            </a>
        </x-brand-bar>

        <header class="mt-5">
            <h1 class="text-2xl font-extrabold">Buku Resep</h1>
            <p class="text-sm text-ink-soft">Temukan dan masak hidangan favorit keluarga.</p>
        </header>

        <div class="mt-4">
            <x-resep-tabs />
        </div>
    @else
        <h1 class="text-2xl font-extrabold">Resep Favorit</h1>
        <p class="text-sm text-ink-soft">Resep yang Anda tandai.</p>
    @endunless

    <input
        type="text"
        wire:model.live.debounce.400ms="search"
        placeholder="Cari resep keluarga..."
        aria-label="Cari resep"
        class="mt-4 w-full rounded-full border border-rule bg-surface px-5 py-3 text-sm text-ink placeholder:text-muted-2 focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
    >

    <div class="-mx-6 mt-4 flex flex-wrap gap-2 px-6">
        <button
            type="button"
            wire:click="$set('category', '')"
            @class(['rounded-full px-4 py-2 text-xs font-bold', 'bg-accent text-white' => $category === '', 'bg-surface text-ink-soft' => $category !== ''])
        >Semua</button>
        @foreach ($categories as $key => $label)
            <button
                type="button"
                wire:click="$set('category', '{{ $key }}')"
                @class(['rounded-full px-4 py-2 text-xs font-bold', 'bg-accent text-white' => $category === $key, 'bg-surface text-ink-soft' => $category !== $key])
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4">
        @forelse ($recipes as $recipe)
            <div class="card-sm relative overflow-hidden">
                <a wire:navigate href="{{ route('recipes.show', $recipe->id) }}" class="block">
                    @if ($recipe->image_url)
                        <img src="{{ $recipe->image_url }}" alt="" class="aspect-[4/3] w-full object-cover" loading="lazy">
                    @else
                        <span class="bg-tint flex aspect-[4/3] w-full items-center justify-center text-3xl" aria-hidden="true">🍽️</span>
                    @endif
                    <span class="block p-3">
                        <span class="block text-sm font-bold">{{ $recipe->name }}</span>
                        <span class="mt-2 flex items-center gap-2 text-xs">
                            @if ($recipe->duration_minutes)
                                <span class="badge badge-accent">🕐 {{ $recipe->duration_minutes }}m</span>
                            @endif
                            @if ($recipe->servings)
                                <span class="badge badge-muted">🍽️ {{ $recipe->servings }}</span>
                            @endif
                        </span>
                    </span>
                </a>
                @auth
                    <span class="absolute right-2 top-2">
                        @livewire('cooking::favorite-button', ['recipeId' => $recipe->id], key('fav-list-'.$recipe->id))
                    </span>
                @endauth
            </div>
        @empty
            <p class="card col-span-2 p-6 text-center text-sm text-ink-soft">Tidak ada resep yang cocok.</p>
        @endforelse
    </div>

    @if ($hasMore)
        <button
            type="button"
            wire:click="loadMore"
            wire:loading.attr="disabled"
            wire:target="loadMore"
            class="mt-4 w-full rounded-full border border-rule bg-surface py-3 text-sm font-bold text-ink-soft disabled:opacity-40"
        >
            <span wire:loading.remove wire:target="loadMore">Muat lebih banyak</span>
            <span wire:loading wire:target="loadMore">Memuat...</span>
        </button>
    @endif

    @unless ($lockFavoritesFilter)
        <x-fab label="Cari resep dari bahan" onclick="window.location='{{ route('cooking.cari') }}'" />
    @endunless
</div>
