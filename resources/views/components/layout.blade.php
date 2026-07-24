<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asisten Mama</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2e7d32">
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="min-h-screen bg-amber-50 font-sans text-stone-800">

    <header class="bg-green-700 text-white px-4 py-3 flex items-center gap-2 sticky top-0 z-10 shadow-sm">
        <span class="text-2xl">🏡</span>
        <span class="font-bold text-lg tracking-tight">Asisten Mama</span>
    </header>

    <main class="pb-24 px-4 pt-4 max-w-lg mx-auto">
        {{ $slot }}
    </main>

    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-stone-200 flex justify-around py-2 z-10">
        <a href="/" class="flex flex-col items-center gap-0.5 text-xs {{ request()->is('/') ? 'text-green-700 font-semibold' : 'text-stone-400' }}">
            <span class="text-xl">🔍</span>
            <span>Cari</span>
        </a>
        <a wire:navigate href="{{ route('recipes.index') }}" class="flex flex-col items-center gap-0.5 text-xs {{ request()->routeIs('recipes.index') ? 'text-green-700 font-semibold' : 'text-stone-400' }}">
            <span class="text-xl">📖</span>
            <span>Resep</span>
        </a>
        <a wire:navigate href="{{ route('shopping-list.index') }}" class="flex flex-col items-center gap-0.5 text-xs {{ request()->routeIs('shopping-list.index') ? 'text-green-700 font-semibold' : 'text-stone-400' }}">
            <span class="text-xl">🛒</span>
            <span>Belanja</span>
        </a>
        <a wire:navigate href="{{ route('finance.index') }}" class="flex flex-col items-center gap-0.5 text-xs {{ request()->routeIs('finance.index') ? 'text-green-700 font-semibold' : 'text-stone-400' }}">
            <span class="text-xl">💰</span>
            <span>Keuangan</span>
        </a>
    </nav>

    @livewireScripts
    <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
    </script>
</body>
</html>
