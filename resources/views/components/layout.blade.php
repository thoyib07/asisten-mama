<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asisten Mama</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#F1E9D6">
    <script>
    (function () {
        var saved = localStorage.getItem('theme');
        if (saved === 'dark' || saved === 'light') document.documentElement.setAttribute('data-theme', saved);
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-theme-toggle]');
            if (!btn) return;
            var current = document.documentElement.getAttribute('data-theme')
                || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        });
    })();
    </script>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="min-h-screen bg-app font-sans text-ink">

    <header class="bg-surface-alt text-ink px-4 py-3 flex items-center gap-2 sticky top-0 z-10 shadow-sm">
        <span class="text-2xl">🏡</span>
        <span class="font-bold text-lg tracking-tight">Asisten Mama</span>
    </header>

    <main class="pb-24 px-4 pt-4 max-w-lg mx-auto">
        {{ $slot }}
    </main>

    <nav class="fixed bottom-0 left-0 right-0 bg-surface-alt border-t border-rule flex justify-around items-end py-2 z-10">
        <a wire:navigate href="{{ route('beranda') }}" class="flex flex-col items-center gap-0.5 text-xs {{ request()->routeIs('beranda') ? 'text-accent font-semibold' : 'text-ink-soft' }}">
            <span class="text-xl">🏠</span>
            <span>Beranda</span>
        </a>
        <a wire:navigate href="{{ route('cooking.cari') }}" class="flex flex-col items-center gap-0.5 text-xs -translate-y-4">
            <span class="bg-accent flex h-12 w-12 items-center justify-center rounded-full text-xl text-white shadow-md {{ request()->routeIs('cooking.cari') ? 'ring-2 ring-offset-2 ring-accent' : '' }}">🔍</span>
            <span class="text-ink-soft">Cari</span>
        </a>
        {{-- Halaman Keluarga/User belum dibangun — nonaktif dulu, bukan route mati --}}
        <span class="flex flex-col items-center gap-0.5 text-xs text-ink-soft opacity-40">
            <span class="text-xl">👨‍👩‍👧</span>
            <span>Keluarga</span>
        </span>
    </nav>

    @livewireScripts
    <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
    </script>
</body>
</html>
