{{-- Ubin grid Beranda (docs/ui-design.md §5.1). Tanpa :href ubin dirender non-aktif. --}}
@props(['label', 'icon', 'tone', 'href' => null, 'count' => 0])

<{{ $href ? 'a' : 'div' }}
    @if ($href) wire:navigate href="{{ $href }}" @else aria-disabled="true" @endif
    @class(['card-sm relative flex flex-col items-center gap-2 p-3 text-center', 'opacity-50' => ! $href])
>
    <span class="{{ $tone }} flex h-12 w-12 items-center justify-center rounded-2xl">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="{{ $icon }}" />
        </svg>
    </span>
    <span class="text-xs font-bold">{{ $label }}</span>
    @if ($count > 0)
        <span class="bg-accent absolute right-2 top-2 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[10px] font-bold text-white">
            {{ $count }}
        </span>
    @endif
</{{ $href ? 'a' : 'div' }}>
