<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asisten Mama</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#00B14F">
    {{-- @fonts memuat @font-face Figtree yang di-self-host oleh bunny() di vite.config.js.
         Tanpa direktif ini font-nya ikut ter-build tapi tidak pernah dipakai — app diam-diam
         jatuh ke font sistem (kondisi Instrument Sans sebelumnya). --}}
    @fonts
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="min-h-screen bg-app font-sans text-ink">

    {{-- Tidak ada header global: tiap halaman merender brand bar / judulnya sendiri sesuai frame. --}}
    <main class="mx-auto max-w-[430px] px-6 pt-4 pb-28">
        {{ $slot }}
    </main>

    @php
        // Aturan state aktif nav (docs/ui-design.md §5.2): slot menyala hanya untuk route miliknya
        // sendiri. Pengecualian tunggal: /tugas menyalakan Kalender (satu pasangan agenda keluarga).
        // Route tanpa slot (/resep, /finance, /favorites, ...) tidak menyalakan apa pun.
        $navSlots = [
            ['route' => 'beranda', 'label' => 'Beranda', 'on' => ['beranda'],
             'icon' => 'M3 10.5 12 3l9 7.5M5.25 9.75V21h13.5V9.75'],
            ['route' => 'kalender', 'label' => 'Kalender', 'on' => ['kalender', 'tugas'],
             'icon' => 'M4 6.75A1.75 1.75 0 0 1 5.75 5h12.5A1.75 1.75 0 0 1 20 6.75v12.5A1.75 1.75 0 0 1 18.25 21H5.75A1.75 1.75 0 0 1 4 19.25zM4 10h16M8 3v4M16 3v4'],
            ['route' => 'shopping-list.index', 'label' => 'Belanja', 'on' => ['shopping-list.index'],
             'icon' => 'M3 4h2l2.4 11.2a1.5 1.5 0 0 0 1.5 1.2h8.2a1.5 1.5 0 0 0 1.5-1.2L21 8H6M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2M18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2'],
            ['route' => 'household.index', 'label' => 'Profil', 'on' => ['household.index'],
             'icon' => 'M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20M9.5 10.5a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5M17 14.3a4 4 0 0 1 3 3.87V20M16 4.3a3.25 3.25 0 0 1 0 6.2'],
        ];
    @endphp

    <nav class="fixed bottom-0 left-1/2 z-10 w-full max-w-[430px] -translate-x-1/2 border-t border-rule bg-surface-alt">
        <div class="flex items-stretch justify-around px-2 pb-2 pt-2.5">
            @foreach ($navSlots as $slot)
                @php $active = request()->routeIs(...$slot['on']); @endphp
                <a
                    wire:navigate
                    href="{{ route($slot['route']) }}"
                    @class([
                        'flex flex-1 flex-col items-center gap-1 rounded-2xl py-1 text-[11px]',
                        'text-accent font-bold' => $active,
                        'text-ink-soft' => ! $active,
                    ])
                    @if ($active) aria-current="page" @endif
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="{{ $slot['icon'] }}" />
                    </svg>
                    <span>{{ $slot['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    @livewireScripts
    <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
    </script>
</body>
</html>
