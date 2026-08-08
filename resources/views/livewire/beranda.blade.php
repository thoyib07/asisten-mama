<div class="space-y-5">
    <header class="space-y-3">
        <div>
            <h1 class="text-xl font-bold text-ink">{{ $greeting }}!</h1>
            <p class="text-sm text-ink-soft">{{ $today }}</p>
        </div>

        @if ($members->isNotEmpty())
            <div class="flex -space-x-2">
                @foreach ($members as $member)
                    <div
                        class="{{ $member->avatarColorClass() }} flex h-9 w-9 items-center justify-center rounded-full border-2 border-surface text-xs font-bold text-white"
                        title="{{ $member->name }}"
                    >
                        {{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex gap-4 rounded-lg border border-rule bg-surface px-4 py-2 text-sm text-ink-soft">
            <span>🛒 {{ $pendingShoppingCount }} belanja pending</span>
            <span>📖 {{ $newRecipeCount }} resep baru</span>
        </div>
    </header>

    <div class="grid grid-cols-3 gap-3">
        <a wire:navigate href="{{ route('recipes.index') }}" class="flex flex-col items-center gap-1 rounded-lg border border-rule bg-surface p-3 text-center text-ink">
            <span class="mod-resep flex h-10 w-10 items-center justify-center rounded-full text-lg">📖</span>
            <span class="text-xs font-semibold">Resep</span>
        </a>
        <a wire:navigate href="{{ route('shopping-list.index') }}" class="flex flex-col items-center gap-1 rounded-lg border border-rule bg-surface p-3 text-center text-ink">
            <span class="mod-belanja flex h-10 w-10 items-center justify-center rounded-full text-lg">🛒</span>
            <span class="text-xs font-semibold">Belanja</span>
        </a>
        <a wire:navigate href="{{ route('finance.index') }}" class="flex flex-col items-center gap-1 rounded-lg border border-rule bg-surface p-3 text-center text-ink">
            <span class="mod-keuangan flex h-10 w-10 items-center justify-center rounded-full text-lg">💰</span>
            <span class="text-xs font-semibold">Keuangan</span>
        </a>
        <div class="flex flex-col items-center gap-1 rounded-lg border border-rule bg-surface p-3 text-center text-ink-soft opacity-50" aria-disabled="true">
            <span class="mod-langganan flex h-10 w-10 items-center justify-center rounded-full text-lg">⭐</span>
            <span class="text-xs font-semibold">Langganan</span>
            <span class="text-[10px]">segera</span>
        </div>
        <div class="flex flex-col items-center gap-1 rounded-lg border border-rule bg-surface p-3 text-center text-ink-soft opacity-50" aria-disabled="true">
            <span class="mod-inventaris flex h-10 w-10 items-center justify-center rounded-full text-lg">📦</span>
            <span class="text-xs font-semibold">Inventaris</span>
            <span class="text-[10px]">segera</span>
        </div>
    </div>
</div>
