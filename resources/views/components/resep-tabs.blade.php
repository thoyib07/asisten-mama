{{-- Dua mode Buku Resep (docs/ui-design.md §5.4). Sengaja dua route + dua tautan, bukan komponen
     Livewire pembungkus: RecipeList & RecipeFinder tetap berdiri sendiri dan tetap bisa dites
     langsung lewat Livewire::test(). --}}
@php
    $tabs = [
        ['label' => 'Jelajah', 'route' => 'recipes.index'],
        ['label' => 'Cari dari bahan', 'route' => 'cooking.cari'],
    ];
@endphp

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
