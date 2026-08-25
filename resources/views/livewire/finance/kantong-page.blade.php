<div>
    <div class="flex items-center gap-3">
        <a wire:navigate href="{{ route('finance.index') }}" aria-label="Kembali ke Keuangan" class="text-ink-soft">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-extrabold">Kantong</h1>
            <p class="text-sm text-ink-soft">Anggaran tiap pos pengeluaran, per periode.</p>
        </div>
    </div>

    <div class="mt-4">
        <select wire:model.live="periodStart" aria-label="Periode"
                class="w-full rounded-full border border-rule bg-surface px-4 py-2.5 text-sm font-bold text-ink">
            @foreach ($periods as $period)
                <option value="{{ $period['value'] }}">{{ $period['title'] }}</option>
            @endforeach
        </select>
        @if ($periodLabel['range'])
            <p class="text-ink-soft mt-1 text-xs">{{ $periodLabel['range'] }}</p>
        @endif
    </div>

    {{-- Kantong = kategori pengeluaran + nominal untuk periode ini. Kantong yang kolom
         nominalnya dibiarkan kosong berarti belum dianggarkan; transaksinya tetap boleh masuk. --}}
    <form wire:submit.prevent="saveAllocations" class="mt-4 space-y-2">
        @foreach ($pockets as $pocket)
            @php $category = $pocket['category']; @endphp
            <div class="card-sm p-4">
                @if ($editingCategoryId === $category->id)
                    <div class="flex gap-2">
                        <input type="text" wire:model="editIcon" placeholder="Ikon" aria-label="Ikon kategori"
                               class="w-16 rounded-full border border-rule bg-app px-3 py-2 text-center text-sm">
                        <input type="text" wire:model="editName" aria-label="Nama kategori"
                               class="min-w-0 flex-1 rounded-full border border-rule bg-app px-4 py-2 text-sm text-ink">
                        <button type="button" wire:click="saveCategory" class="bg-accent rounded-full px-4 py-2 text-xs font-bold text-white">Simpan</button>
                        <button type="button" wire:click="$set('editingCategoryId', null)" class="text-ink-soft px-2 text-xs font-bold">Batal</button>
                    </div>
                    @error('editName') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
                @else
                    <div class="flex items-center justify-between gap-2">
                        <p class="min-w-0 truncate text-sm font-bold">{{ $category->icon }} {{ $category->name }}</p>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" wire:click="editCategory({{ $category->id }})" class="text-ink-soft text-xs font-bold">Ubah</button>
                            @if ($category->canBeArchived())
                                <button type="button" wire:click="archive({{ $category->id }})"
                                        wire:confirm="Kantong ini hilang dari daftar, tapi transaksi lamanya tetap tersimpan. Arsipkan?"
                                        class="text-danger text-xs font-bold">Arsipkan</button>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="mt-2 flex items-center gap-2">
                    <span class="text-ink-soft shrink-0 text-xs">Rp</span>
                    <input type="number" step="0.01" min="0" wire:model="amounts.{{ $category->id }}"
                           placeholder="Belum dianggarkan" aria-label="Anggaran {{ $category->name }}"
                           class="min-w-0 flex-1 rounded-full border border-rule bg-app px-4 py-2 text-sm text-ink placeholder:text-muted-2">
                    @if ($pocket['allocation'])
                        <button type="button" wire:click="$set('topupFor', {{ $pocket['allocation']->id }})"
                                class="bg-tint text-accent shrink-0 rounded-full px-3 py-2 text-xs font-bold">Top up</button>
                    @endif
                </div>
                @error('amounts.'.$category->id) <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror

                @if ($pocket['budget'] !== null)
                    @php $ratio = $pocket['budget'] > 0 ? min(1, $pocket['spent'] / $pocket['budget']) : 1; @endphp
                    <div class="bg-rule mt-2 h-1.5 overflow-hidden rounded-full">
                        <div class="h-full rounded-full {{ $pocket['remaining'] < 0 ? 'bg-[var(--danger)]' : 'bg-accent' }}"
                             style="width: {{ $ratio * 100 }}%"></div>
                    </div>
                    <p class="text-ink-soft mt-1 text-xs">
                        Terpakai Rp{{ number_format($pocket['spent'], 0, ',', '.') }} dari Rp{{ number_format($pocket['budget'], 0, ',', '.') }}
                        &bull; <span class="{{ $pocket['remaining'] < 0 ? 'text-danger font-bold' : '' }}">sisa Rp{{ number_format($pocket['remaining'], 0, ',', '.') }}</span>
                    </p>
                @else
                    <p class="text-ink-soft mt-1 text-xs">
                        Belum dianggarkan &bull; terpakai Rp{{ number_format($pocket['spent'], 0, ',', '.') }}
                    </p>
                @endif

                @if ($topupFor === $pocket['allocation']?->id && $pocket['allocation'])
                    <div class="border-rule mt-3 border-t pt-3">
                        <p class="text-ink-soft mb-2 text-xs">Tambah anggaran di tengah periode. Nominal awal tetap tercatat apa adanya.</p>
                        <div class="flex gap-2">
                            <input type="number" step="0.01" min="0.01" wire:model="topupAmount" placeholder="Nominal tambahan"
                                   aria-label="Nominal top-up"
                                   class="min-w-0 flex-1 rounded-full border border-rule bg-app px-4 py-2 text-sm text-ink placeholder:text-muted-2">
                            <button type="button" wire:click="topup" class="bg-accent shrink-0 rounded-full px-4 py-2 text-xs font-bold text-white">Tambah</button>
                            <button type="button" wire:click="$set('topupFor', null)" class="text-ink-soft px-2 text-xs font-bold">Batal</button>
                        </div>
                        @error('topupAmount') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        @endforeach

        <button type="submit" class="bg-accent w-full rounded-full py-3 font-bold text-white">Simpan anggaran</button>
    </form>

    @if ($adding)
        <form wire:submit.prevent="addCategory" class="card mt-4 space-y-3 p-5">
            <div class="flex gap-2">
                <button type="button" wire:click="$set('newType', 'expense')"
                    @class(['flex-1 rounded-full py-2 text-xs font-bold', 'bg-accent text-white' => $newType === 'expense', 'bg-app text-ink-soft border border-rule' => $newType !== 'expense'])>Kantong pengeluaran</button>
                <button type="button" wire:click="$set('newType', 'income')"
                    @class(['flex-1 rounded-full py-2 text-xs font-bold', 'bg-accent text-white' => $newType === 'income', 'bg-app text-ink-soft border border-rule' => $newType !== 'income'])>Kategori pemasukan</button>
            </div>

            <div class="flex gap-2">
                <input type="text" wire:model="newIcon" placeholder="🍼" aria-label="Ikon"
                       class="w-16 rounded-full border border-rule bg-app px-3 py-2.5 text-center text-sm">
                <input type="text" wire:model="newName" placeholder="Nama, mis. Kebutuhan Bayi" aria-label="Nama"
                       class="min-w-0 flex-1 rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink placeholder:text-muted-2">
            </div>
            @error('newName') <p class="text-danger text-xs">{{ $message }}</p> @enderror

            @if ($newType === 'expense')
                <div>
                    <input type="number" step="0.01" min="0" wire:model="newAmount" placeholder="Anggaran periode ini (Rp)"
                           aria-label="Anggaran awal"
                           class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink placeholder:text-muted-2">
                    @error('newAmount') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex gap-2">
                <button type="submit" class="bg-accent flex-1 rounded-full py-3 font-bold text-white">Tambah</button>
                <button type="button" wire:click="$set('adding', false)"
                        class="bg-app rounded-full border border-rule px-5 py-3 text-sm font-bold text-ink-soft">Batal</button>
            </div>
        </form>
    @else
        <button type="button" wire:click="$set('adding', true)"
                class="border-rule text-ink-soft mt-3 w-full rounded-full border border-dashed py-3 text-sm font-bold">
            + Tambah kantong / kategori
        </button>
    @endif

    @if ($incomeCategories->isNotEmpty())
        <section class="mt-6">
            <h2 class="text-sm font-bold">Kategori pemasukan</h2>
            <p class="text-ink-soft text-xs">Pemasukan tidak punya anggaran — kategorinya cuma untuk pengelompokan.</p>
            <div class="mt-2 space-y-2">
                @foreach ($incomeCategories as $category)
                    <div class="card-sm flex items-center justify-between gap-2 p-4">
                        @if ($editingCategoryId === $category->id)
                            <input type="text" wire:model="editIcon" aria-label="Ikon kategori"
                                   class="w-16 rounded-full border border-rule bg-app px-3 py-2 text-center text-sm">
                            <input type="text" wire:model="editName" aria-label="Nama kategori"
                                   class="min-w-0 flex-1 rounded-full border border-rule bg-app px-4 py-2 text-sm text-ink">
                            <button type="button" wire:click="saveCategory" class="bg-accent shrink-0 rounded-full px-4 py-2 text-xs font-bold text-white">Simpan</button>
                        @else
                            <p class="min-w-0 truncate text-sm font-bold">{{ $category->icon }} {{ $category->name }}</p>
                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" wire:click="editCategory({{ $category->id }})" class="text-ink-soft text-xs font-bold">Ubah</button>
                                @if ($category->canBeArchived())
                                    <button type="button" wire:click="archive({{ $category->id }})" class="text-danger text-xs font-bold">Arsipkan</button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($archived->isNotEmpty())
        <section class="mt-6">
            <h2 class="text-sm font-bold">Diarsipkan</h2>
            <p class="text-ink-soft text-xs">Tidak muncul saat mencatat transaksi. Riwayat lamanya tetap utuh.</p>
            <div class="mt-2 space-y-2">
                @foreach ($archived as $category)
                    <div class="card-sm flex items-center justify-between gap-2 p-4">
                        <p class="text-ink-soft min-w-0 truncate text-sm">{{ $category->icon }} {{ $category->name }}</p>
                        <button type="button" wire:click="unarchive({{ $category->id }})" class="text-accent shrink-0 text-xs font-bold">Pulihkan</button>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="card mt-6 p-5">
        <h2 class="text-sm font-bold">Tanggal mulai periode</h2>
        <p class="text-ink-soft text-xs">
            Bisa disamakan dengan tanggal gajian. Perubahan berlaku mulai periode berikutnya —
            periode yang sedang berjalan tetap selesai dengan batas yang lama.
        </p>
        <div class="mt-3 flex gap-2">
            <input type="number" min="1" max="28" wire:model="resetDay" aria-label="Tanggal mulai periode"
                   class="w-24 rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink">
            <button type="button" wire:click="saveResetDay"
                    class="bg-app rounded-full border border-rule px-4 py-2.5 text-xs font-bold text-ink-soft">Simpan</button>
        </div>
        @error('resetDay') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
    </section>
</div>
