<div class="space-y-5">
    <div>
        <h1 class="text-xl font-bold text-stone-800">Mau masak apa hari ini?</h1>
        <p class="text-sm text-stone-400">Masukkan bahan yang ada di dapur, kami carikan resepnya.</p>
    </div>

    {{-- Input tambah bahan --}}
    <form wire:submit.prevent="addIngredient" class="flex gap-2">
        <input
            type="text"
            wire:model="newIngredient"
            placeholder="Tambah bahan... (misal: telur)"
            aria-label="Nama bahan"
            class="flex-1 rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm text-stone-800 focus:outline-none focus:ring-2 focus:ring-green-600 placeholder-stone-400"
        >
        <button
            type="submit"
            class="rounded-xl bg-green-700 px-4 py-3 text-sm font-bold text-white active:bg-green-800"
        >
            Tambah
        </button>
    </form>

    @if ($ingredientError)
        <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600" role="alert">{{ $ingredientError }}</p>
    @endif

    {{-- Chip bahan yang sudah ditambahkan --}}
    @if ($ingredients)
    <div class="flex flex-wrap gap-2">
        @foreach ($ingredients as $i => $ing)
            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">
                {{ $ing }}
                <button wire:click="removeIngredient({{ $i }})" aria-label="Hapus {{ $ing }}" class="text-green-600 hover:text-green-900 leading-none">&times;</button>
            </span>
        @endforeach
    </div>
    @endif

    {{-- Filter kategori makan & jenis masakan (opsional) --}}
    <div class="space-y-2">
        <p class="text-xs font-semibold text-stone-500">Kategori makan (opsional)</p>
        <div class="flex flex-wrap gap-2">
            @foreach (\App\Modules\Cooking\Support\RecipeTaxonomy::MEAL_CATEGORIES as $value => $label)
                <label class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-stone-200 bg-white px-3 py-1 text-xs font-medium text-stone-600 has-[:checked]:border-green-600 has-[:checked]:bg-green-50 has-[:checked]:text-green-800">
                    <input type="checkbox" wire:model="selectedMealCategories" value="{{ $value }}" class="sr-only">
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <p class="text-xs font-semibold text-stone-500">Jenis masakan (opsional)</p>
        <div class="flex flex-wrap gap-2">
            @foreach (\App\Modules\Cooking\Support\RecipeTaxonomy::CUISINE_TYPES as $value => $label)
                <label class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-stone-200 bg-white px-3 py-1 text-xs font-medium text-stone-600 has-[:checked]:border-green-600 has-[:checked]:bg-green-50 has-[:checked]:text-green-800">
                    <input type="checkbox" wire:model="selectedCuisineTypes" value="{{ $value }}" class="sr-only">
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    {{-- Tombol cari --}}
    <button
        wire:click="search"
        @disabled(empty($ingredients))
        class="w-full rounded-xl bg-green-700 py-3 font-bold text-white shadow-sm active:bg-green-800 disabled:opacity-40 disabled:cursor-not-allowed"
    >
        🔍 Cari Resep
    </button>

    {{-- Tombol AI --}}
    <div>
        <button
            wire:click="exploreWithAi"
            wire:loading.attr="disabled"
            @disabled(empty($ingredients) || ! $searched)
            class="w-full rounded-xl border border-orange-200 bg-orange-50 py-3 font-bold text-orange-600 active:bg-orange-100 disabled:opacity-40 disabled:cursor-not-allowed"
        >
            <span wire:loading.remove wire:target="exploreWithAi">✨ Eksplor dengan AI</span>
            <span wire:loading wire:target="exploreWithAi">Mencari ide resep AI...</span>
        </button>
        @if (! empty($ingredients) && ! $searched)
            <p class="mt-2 text-xs text-stone-400">Cari resep di database dulu sebelum eksplorasi AI.</p>
        @endif
        @if ($aiNotice)
            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700" role="status">{{ $aiNotice }}</p>
        @endif
        @if ($aiError)
            <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600" role="alert">{{ $aiError }}</p>
        @endif
    </div>

    {{-- Hasil pencarian --}}
    @if ($searched)
        <section class="space-y-3">
            @forelse ($results as $r)
                <div class="space-y-2 rounded-xl bg-white p-4 shadow-sm active:shadow-none">
                    <div class="flex items-start gap-2">
                        <a wire:navigate href="{{ route('recipes.show', $r['id']) }}" class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-stone-800">{{ $r['name'] }}</span>
                                @php $pct = (int) round($r['score'] * 100); @endphp
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold
                                    {{ $pct >= 80 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $pct }}% cocok
                                </span>
                            </div>
                            @if (!empty($r['missing']))
                                <p class="mt-1 text-xs text-stone-400">Kurang: {{ implode(', ', $r['missing']) }}</p>
                            @endif
                            @if ($r['source'] === 'ai')
                                <p class="mt-1 text-xs text-orange-500">✨ Resep dari AI — cek kematangan &amp; kebersihan sendiri.</p>
                            @endif
                        </a>
                        @auth
                            @livewire('cooking::favorite-button', ['recipeId' => $r['id']], key('fav-finder-'.$r['id']))
                        @endauth
                    </div>
                    @auth
                        @if (!empty($r['missing']))
                            @livewire('shopping-list::add-missing-ingredients', ['recipeId' => $r['id'], 'missing' => $r['missing']], key('missing-'.$r['id']))
                        @endif
                    @endauth
                </div>
            @empty
                <p class="rounded-xl bg-white p-4 text-center text-sm text-stone-400 shadow-sm">
                    Tidak ada resep yang cukup cocok. Coba Eksplor dengan AI!
                </p>
            @endforelse
        </section>
    @endif
</div>
