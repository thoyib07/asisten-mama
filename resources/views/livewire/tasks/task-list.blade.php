<div>
    <h1 class="text-2xl font-extrabold">Daftar Tugas</h1>

    <div class="card mt-4 flex gap-1 p-1.5">
        @foreach (['semua' => 'Semua', 'saya' => 'Saya', 'keluarga' => 'Keluarga'] as $key => $label)
            <button
                type="button"
                wire:click="$set('filter', '{{ $key }}')"
                @class([
                    'flex-1 rounded-full py-2 text-center text-sm',
                    'bg-accent font-bold text-white' => $filter === $key,
                    'font-semibold text-ink-soft' => $filter !== $key,
                ])
                @if ($filter === $key) aria-current="true" @endif
            >{{ $label }}</button>
        @endforeach
    </div>

    {{-- Form tambah: tidak ada frame-nya di Figma. Dibangun dari komponen sistem yang sudah ada
         (kartu, pill, chip) — tanpa ini modul Tugas tidak punya cara mengisi data sama sekali. --}}
    @if ($adding)
        <form wire:submit.prevent="addTask" class="card mt-4 space-y-3 p-5">
            <div>
                <input
                    type="text"
                    wire:model="title"
                    placeholder="Apa yang perlu dikerjakan?"
                    aria-label="Judul tugas"
                    class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink placeholder:text-muted-2 focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
                >
                @error('title') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2">
                <select wire:model="userId" aria-label="Penanggung jawab"
                        class="min-w-0 flex-1 rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink">
                    <option value="">Belum ditugaskan</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </select>
                <input type="date" wire:model="dueOn" aria-label="Tenggat"
                       class="min-w-0 flex-1 rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink">
            </div>

            <div class="flex gap-2">
                @foreach ($priorities as $key => $label)
                    <button
                        type="button"
                        wire:click="$set('priority', '{{ $key }}')"
                        @class([
                            'flex-1 rounded-full py-2 text-xs font-bold',
                            'bg-accent text-white' => $priority === $key,
                            'bg-app text-ink-soft border border-rule' => $priority !== $key,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>

            <div class="flex gap-2">
                <button type="button" wire:click="toggleForm"
                        class="bg-app flex-1 rounded-full border border-rule py-2.5 text-sm font-bold text-ink-soft">
                    Batal
                </button>
                <button type="submit" class="bg-accent flex-1 rounded-full py-2.5 text-sm font-bold text-white">
                    Simpan
                </button>
            </div>
        </form>
    @endif

    <div class="mt-4 space-y-3">
        @forelse ($tasks as $task)
            <div class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <h2 @class(['font-bold', 'text-ink-soft line-through' => $task->is_done])>{{ $task->title }}</h2>
                    <button
                        type="button"
                        wire:click="toggleTask({{ $task->id }})"
                        @class([
                            'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2',
                            'bg-accent border-transparent text-white' => $task->is_done,
                            'border-rule' => ! $task->is_done,
                        ])
                        aria-pressed="{{ $task->is_done ? 'true' : 'false' }}"
                        aria-label="Tandai selesai: {{ $task->title }}"
                    >
                        @if ($task->is_done)
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4 10-10" /></svg>
                        @endif
                    </button>
                </div>

                <div class="mt-3 flex items-center justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-2 text-xs text-ink-soft">
                        @if ($task->user)
                            <span class="{{ $task->user->avatarColorClass() }} flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white">
                                {{ mb_strtoupper(mb_substr($task->user->name, 0, 1)) }}
                            </span>
                            <span class="truncate">{{ $task->user->name }}</span>
                        @else
                            <span class="truncate">Belum ditugaskan</span>
                        @endif
                    </span>
                    <span class="flex shrink-0 items-center gap-1.5">
                        @if ($task->dueLabel())
                            <span class="badge badge-accent">{{ $task->dueLabel() }}</span>
                        @endif
                        <span class="badge {{ $task->priorityBadgeClass() }}">{{ $task->priorityLabel() }}</span>
                        <button
                            type="button"
                            wire:click="removeTask({{ $task->id }})"
                            wire:confirm="Hapus tugas &quot;{{ $task->title }}&quot;?"
                            class="text-ink-soft"
                            aria-label="Hapus {{ $task->title }}"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                 stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </span>
                </div>
            </div>
        @empty
            <p class="card p-6 text-center text-sm text-ink-soft">
                Belum ada tugas{{ $filter === 'semua' ? '' : ' di filter ini' }}. Tekan tombol + untuk menambah.
            </p>
        @endforelse
    </div>

    <div class="h-20" aria-hidden="true"></div>

    <x-fab label="Tambah tugas" wire:click="toggleForm" />
</div>
