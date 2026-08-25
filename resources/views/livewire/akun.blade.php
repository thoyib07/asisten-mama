<div>
    <h1 class="text-xl font-extrabold">Akun</h1>
    <p class="text-ink-soft text-sm">Kelola nama, password, dan sesi masuk Anda.</p>

    <form wire:submit.prevent="saveProfile" class="card mt-4 space-y-3 p-5">
        <h2 class="font-bold">Profil</h2>

        <div>
            <input type="text" wire:model="name" aria-label="Nama"
                   class="border-rule bg-app text-ink placeholder:text-muted-2 w-full rounded-full border px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--accent)] focus:outline-none">
            @error('name') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
        </div>

        <p class="text-ink-soft text-xs">{{ auth()->user()->email }}</p>

        @if (session('profile-saved'))
            <p class="text-accent text-xs font-semibold">{{ session('profile-saved') }}</p>
        @endif

        <button type="submit" class="bg-accent w-full rounded-full py-2.5 text-sm font-bold text-white">Simpan</button>
    </form>

    <form wire:submit.prevent="updatePassword" class="card mt-4 space-y-3 p-5">
        <h2 class="font-bold">Ganti password</h2>

        <div>
            <input type="password" wire:model="current_password" placeholder="Password sekarang"
                   aria-label="Password sekarang" autocomplete="current-password"
                   class="border-rule bg-app text-ink placeholder:text-muted-2 w-full rounded-full border px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--accent)] focus:outline-none">
            @error('current_password') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
        </div>

        <div>
            <input type="password" wire:model="password" placeholder="Password baru"
                   aria-label="Password baru" autocomplete="new-password"
                   class="border-rule bg-app text-ink placeholder:text-muted-2 w-full rounded-full border px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--accent)] focus:outline-none">
            @error('password') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
        </div>

        <div>
            <input type="password" wire:model="password_confirmation" placeholder="Ulangi password baru"
                   aria-label="Ulangi password baru" autocomplete="new-password"
                   class="border-rule bg-app text-ink placeholder:text-muted-2 w-full rounded-full border px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--accent)] focus:outline-none">
        </div>

        @if (session('password-saved'))
            <p class="text-accent text-xs font-semibold">{{ session('password-saved') }}</p>
        @endif

        <button type="submit" class="bg-accent w-full rounded-full py-2.5 text-sm font-bold text-white">Ganti password</button>
    </form>

    <div class="card mt-4 p-5">
        <button
            wire:click="logout"
            wire:confirm="Keluar dari akun?"
            class="text-danger w-full text-sm font-bold"
        >Keluar dari akun</button>
    </div>
</div>
