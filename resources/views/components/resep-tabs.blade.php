{{-- Dua mode Buku Resep (docs/ui-design.md §5.4). Sengaja dua route + dua tautan, bukan komponen
     Livewire pembungkus: RecipeList & RecipeFinder tetap berdiri sendiri dan tetap bisa dites
     langsung lewat Livewire::test(). --}}
@php
    $tabs = [
        ['label' => 'Jelajah', 'route' => 'recipes.index'],
        // Cuma untuk yang sudah masuk: /resep/bahan ada di belakang `auth` (lihat komentar di
        // Cooking/routes/web.php). Menampilkannya ke tamu cuma memantulkan mereka ke login.
        ['label' => 'Cari dari bahan', 'route' => 'cooking.cari', 'auth' => true],
    ];

    $tabs = array_values(array_filter(
        $tabs,
        fn (array $tab) => ! ($tab['auth'] ?? false) || auth()->check()
    ));
@endphp

{{-- Satu tab bukan tab: untuk tamu yang cuma punya "Jelajah", pilihannya disembunyikan
     seluruhnya daripada merender kontrol yang tidak bisa mengganti apa pun. --}}
@if (count($tabs) > 1)
    <div class="card flex gap-1 p-1.5">
        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['route']); @endphp
            <a
                wire:navigate
                href="{{ route($tab['route']) }}"
                @class([
                    'flex-1 rounded-full py-2 text-center text-sm',
                    'bg-accent font-bold text-white' => $active,
                    'font-semibold text-ink-soft' => ! $active,
                ])
                @if ($active) aria-current="page" @endif
            >{{ $tab['label'] }}</a>
        @endforeach
    </div>
@endif
