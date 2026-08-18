<div>
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
        <p class="text-sm text-ink-soft">Masukkan bahan yang ada di dapur, kami carikan resepnya.</p>
    </header>

    <div class="mt-4">
        <x-resep-tabs />
    </div>

    <form wire:submit.prevent="addIngredient" class="mt-4 flex gap-2">
        <input
            type="text"
            wire:model="newIngredient"
            placeholder="Tambah bahan... (misal: telur)"
            aria-label="Nama bahan"
            class="min-w-0 flex-1 rounded-full border border-rule bg-surface px-5 py-3 text-sm text-ink placeholder:text-muted-2 focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
        >
        <button type="submit" class="bg-accent shrink-0 rounded-full px-5 py-3 text-sm font-bold text-white">
            Tambah
        </button>
    </form>

    @if ($ingredientError)
        <p class="badge-danger mt-3 rounded-2xl px-4 py-2 text-sm" role="alert">{{ $ingredientError }}</p>
    @endif

    @if ($ingredients)
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($ingredients as $i => $ing)
                <span class="badge badge-accent inline-flex items-center gap-1.5 px-3 py-1.5 text-sm">
                    {{ $ing }}
                    <button wire:click="removeIngredient({{ $i }})" aria-label="Hapus {{ $ing }}" class="leading-none">&times;</button>
                </span>
            @endforeach
        </div>
    @endif

    <div class="mt-5 space-y-3">
        <p class="text-xs font-bold text-ink-soft">Kategori makan (opsional)</p>
        <div class="flex flex-wrap gap-2">
            @foreach (\App\Modules\Cooking\Support\RecipeTaxonomy::MEAL_CATEGORIES as $value => $label)
                <label class="bg-surface inline-flex cursor-pointer items-center rounded-full border border-rule px-4 py-2 text-xs font-bold text-ink-soft has-[:checked]:border-transparent has-[:checked]:bg-[var(--accent)] has-[:checked]:text-white">
                    <input type="checkbox" wire:model="selectedMealCategories" value="{{ $value }}" class="sr-only">
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <p class="text-xs font-bold text-ink-soft">Jenis masakan (opsional)</p>
        <div class="flex flex-wrap gap-2">
            @foreach (\App\Modules\Cooking\Support\RecipeTaxonomy::CUISINE_TYPES as $value => $label)
                <label class="bg-surface inline-flex cursor-pointer items-center rounded-full border border-rule px-4 py-2 text-xs font-bold text-ink-soft has-[:checked]:border-transparent has-[:checked]:bg-[var(--accent)] has-[:checked]:text-white">
                    <input type="checkbox" wire:model="selectedCuisineTypes" value="{{ $value }}" class="sr-only">
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <button
        wire:click="search"
        @disabled(empty($ingredients))
        class="bg-accent mt-5 w-full rounded-full py-3.5 font-bold text-white disabled:cursor-not-allowed disabled:opacity-40"
    >
        🔍 Cari Resep
    </button>

    <div class="mt-3">
        <button
            wire:click="exploreWithAi"
            wire:loading.attr="disabled"
            @disabled(empty($ingredients) || ! $searched)
            class="bg-surface w-full rounded-full border border-rule py-3.5 font-bold text-ink disabled:cursor-not-allowed disabled:opacity-40"
        >
            <span wire:loading.remove wire:target="exploreWithAi">✨ Eksplor dengan AI</span>
            <span wire:loading wire:target="exploreWithAi">Mencari ide resep AI...</span>
        </button>
        @if (! empty($ingredients) && ! $searched)
            <p class="mt-2 text-xs text-ink-soft">Cari resep di database dulu sebelum eksplorasi AI.</p>
        @endif
        @if ($aiNotice)
            <p class="badge-warning mt-2 rounded-2xl px-4 py-2 text-sm" role="status">{{ $aiNotice }}</p>
        @endif
        @if ($aiError)
            <p class="badge-danger mt-2 rounded-2xl px-4 py-2 text-sm" role="alert">{{ $aiError }}</p>
        @endif
    </div>

    @if ($searched)
        <div class="mt-5 space-y-3">
            @forelse ($results as $r)
                <div class="card p-4">
                    <div class="flex items-start gap-2">
                        <a wire:navigate href="{{ route('recipes.show', $r['id']) }}" class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="font-bold">{{ $r['name'] }}</span>
                                @php $pct = (int) round($r['score'] * 100); @endphp
                                <span class="badge shrink-0 {{ $pct >= 80 ? 'badge-accent' : 'badge-warning' }}">{{ $pct }}% cocok</span>
                            </span>
                            @if (! empty($r['missing']))
                                <span class="mt-1 block text-xs text-ink-soft">Kurang: {{ implode(', ', $r['missing']) }}</span>
                            @endif
                            @if ($r['source'] === 'ai')
                                <span class="mt-1 block text-xs text-ink-soft">✨ Resep dari AI — cek kematangan &amp; kebersihan sendiri.</span>
                            @endif
                        </a>
                        @auth
                            @livewire('cooking::favorite-button', ['recipeId' => $r['id']], key('fav-finder-'.$r['id']))
                        @endauth
                    </div>
                    @auth
                        @if (! empty($r['missing']))
                            <div class="mt-2">
                                @livewire('shopping-list::add-missing-ingredients', ['recipeId' => $r['id'], 'missing' => $r['missing']], key('missing-'.$r['id']))
                            </div>
                        @endif
                    @endauth
                </div>
            @empty
                <p class="card p-6 text-center text-sm text-ink-soft">
                    Tidak ada resep yang cukup cocok. Coba Eksplor dengan AI!
                </p>
            @endforelse
        </div>
    @endif
</div>
