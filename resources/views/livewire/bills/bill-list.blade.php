<div>
    <h1 class="text-2xl font-extrabold">Tagihan</h1>
    <p class="text-sm text-ink-soft">Pengingat jatuh tempo, tercatat otomatis ke Keuangan saat lunas.</p>

    {{-- Panel koneksi kalender. Sengaja menyebut batasannya apa adanya: ekspektasi keliru di
         sini (mengira langsung masuk kalender keluarga, atau langsung ter-update) akan
         dilaporkan sebagai bug padahal perilaku Google. --}}
    <div class="card mt-4 p-5">
        <button type="button" wire:click="$toggle('showConnect')"
                class="flex w-full items-center justify-between gap-3 text-left"
                aria-expanded="{{ $showConnect ? 'true' : 'false' }}">
            <span class="flex items-center gap-2 font-bold">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 6.75A1.75 1.75 0 0 1 5.75 5h12.5A1.75 1.75 0 0 1 20 6.75v12.5A1.75 1.75 0 0 1 18.25 21H5.75A1.75 1.75 0 0 1 4 19.25zM4 10h16M8 3v4M16 3v4" />
                </svg>
                Hubungkan ke Google Calendar
            </span>
            <span class="text-ink-soft text-xs">{{ $showConnect ? 'Tutup' : 'Buka' }}</span>
        </button>

        @if ($showConnect)
            <div class="mt-4 space-y-3 text-sm">
                <ol class="list-decimal space-y-1 pl-5 text-ink-soft">
                    <li>Buka Google Calendar di komputer.</li>
                    <li>Di panel kiri: <strong>Kalender lain</strong> → <strong>Dari URL</strong>.</li>
                    <li>Tempel alamat di bawah, lalu <strong>Tambahkan kalender</strong>.</li>
                </ol>

                <input
                    type="text"
                    value="{{ $feedUrl }}"
                    readonly
                    onfocus="this.select()"
                    aria-label="Alamat feed kalender tagihan"
                    class="w-full rounded-full border border-rule bg-app px-4 py-2.5 font-mono text-xs text-ink"
                >

                <p class="text-ink-soft text-xs">
                    Ayah dan ibu masing-masing menambahkan alamat ini sekali di akun Google mereka —
                    hasilnya kalender baru, bukan masuk ke kalender keluarga yang sudah ada.
                    Perubahan menyusul dalam 12–24 jam; jadwalnya ditentukan Google dan tidak bisa dipercepat.
                </p>

                <button type="button" wire:click="regenerateCalendarUrl"
                        wire:confirm="Alamat lama langsung berhenti berfungsi dan tiap anggota harus berlangganan ulang. Lanjutkan?"
                        class="bg-app rounded-full border border-rule px-4 py-2 text-xs font-bold text-ink-soft">
                    Ganti alamat (cabut akses lama)
                </button>
            </div>
        @endif
    </div>

    @if ($adding)
        <form wire:submit.prevent="save" class="card mt-4 space-y-3 p-5">
            <div>
                <input type="text" wire:model="name" placeholder="Nama tagihan, mis. Listrik PLN"
                       aria-label="Nama tagihan"
                       class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink placeholder:text-muted-2 focus:outline-none focus:ring-2 focus:ring-[var(--accent)]">
                @error('name') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2">
                <input type="number" step="0.01" min="0" wire:model="amountEstimate" placeholder="Perkiraan nominal"
                       aria-label="Perkiraan nominal"
                       class="min-w-0 flex-1 rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink placeholder:text-muted-2">
                <select wire:model="categoryId" aria-label="Kantong pengeluaran"
                        class="min-w-0 flex-1 rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink">
                    <option value="">Kantong: Tagihan</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->icon }} {{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            @error('amountEstimate') <p class="text-danger text-xs">{{ $message }}</p> @enderror

            <div class="flex gap-2">
                <label class="min-w-0 flex-1">
                    <span class="text-ink-soft mb-1 block text-xs">Jatuh tempo pertama</span>
                    <input type="date" wire:model.live="startsOn" aria-label="Jatuh tempo pertama"
                           class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink">
                </label>
                <label class="min-w-0 flex-1">
                    <span class="text-ink-soft mb-1 block text-xs">Ingatkan (hari sebelum)</span>
                    <input type="number" min="0" max="30" wire:model="reminderDaysBefore" aria-label="Ingatkan berapa hari sebelumnya"
                           class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink">
                </label>
            </div>
            @error('startsOn') <p class="text-danger text-xs">{{ $message }}</p> @enderror
            @error('reminderDaysBefore') <p class="text-danger text-xs">{{ $message }}</p> @enderror

            <div>
                <span class="text-ink-soft mb-1 block text-xs">Pengulangan</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($repeats as $key => $label)
                        <button type="button" wire:click="$set('repeat', '{{ $key }}')"
                                @class([
                                    'rounded-full px-3 py-2 text-xs font-bold',
                                    'bg-accent text-white' => $repeat === $key,
                                    'bg-app text-ink-soft border border-rule' => $repeat !== $key,
                                ])>{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            @if ($repeat === 'lanjutan')
                <div>
                    <input type="text" wire:model="customRrule" placeholder="FREQ=MONTHLY;BYMONTHDAY=20"
                           aria-label="Aturan pengulangan RRULE"
                           class="w-full rounded-full border border-rule bg-app px-4 py-2.5 font-mono text-xs text-ink placeholder:text-muted-2">
                    <p class="text-ink-soft mt-1 text-xs">Aturan pengulangan iCalendar (RFC 5545).</p>
                    @error('customRrule') <p class="text-danger mt-1 text-xs">{{ $message }}</p> @enderror
                </div>
            @endif

            <textarea wire:model="notes" rows="2" placeholder="Catatan (opsional), mis. nomor pelanggan"
                      aria-label="Catatan"
                      class="w-full rounded-2xl border border-rule bg-app px-4 py-2.5 text-sm text-ink placeholder:text-muted-2"></textarea>

            <div class="flex gap-2">
                <button type="button" wire:click="toggleForm"
                        class="bg-app flex-1 rounded-full border border-rule py-2.5 text-sm font-bold text-ink-soft">Batal</button>
                <button type="submit" class="bg-accent flex-1 rounded-full py-2.5 text-sm font-bold text-white">
                    {{ $editingId ? 'Simpan perubahan' : 'Simpan' }}
                </button>
            </div>
        </form>
    @endif

    <div class="mt-4 space-y-3">
        @forelse ($bills as $bill)
            @php($nextDue = $due[$bill->id] ?? null)
            <div class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate font-bold">{{ $bill->name }}</h2>
                        <p class="text-ink-soft mt-0.5 text-xs">
                            @if ($nextDue)
                                Jatuh tempo {{ \Carbon\Carbon::parse($nextDue)->translatedFormat('j M Y') }}
                            @else
                                Tidak ada jatuh tempo berikutnya
                            @endif
                            @if ($bill->amount_estimate !== null)
                                · ±Rp {{ number_format((float) $bill->amount_estimate, 0, ',', '.') }}
                            @endif
                        </p>
                    </div>
                    @if ($nextDue && \Carbon\Carbon::parse($nextDue)->isPast())
                        <span class="badge badge-danger shrink-0">Telat</span>
                    @endif
                </div>

                @if ($payingBillId === $bill->id)
                    <div class="mt-3 space-y-2">
                        <label class="block">
                            <span class="text-ink-soft mb-1 block text-xs">
                                Nominal asli untuk {{ \Carbon\Carbon::parse($payingPeriod)->translatedFormat('F Y') }}
                            </span>
                            <input type="number" step="0.01" min="0.01" wire:model="payAmount"
                                   aria-label="Nominal yang dibayar"
                                   class="w-full rounded-full border border-rule bg-app px-4 py-2.5 text-sm text-ink">
                        </label>
                        @error('payAmount') <p class="text-danger text-xs">{{ $message }}</p> @enderror
                        <div class="flex gap-2">
                            <button type="button" wire:click="cancelPaying"
                                    class="bg-app flex-1 rounded-full border border-rule py-2 text-xs font-bold text-ink-soft">Batal</button>
                            <button type="button" wire:click="confirmPaying"
                                    class="bg-accent flex-1 rounded-full py-2 text-xs font-bold text-white">Catat pembayaran</button>
                        </div>
                    </div>
                @else
                    <div class="mt-3 flex items-center justify-between gap-2">
                        <span class="badge badge-accent shrink-0">
                            {{ $bill->category?->name ?? 'Tagihan' }}
                        </span>
                        <span class="flex shrink-0 items-center gap-1.5">
                            @if ($nextDue)
                                <button type="button" wire:click="startPaying({{ $bill->id }}, '{{ $nextDue }}')"
                                        class="bg-accent rounded-full px-3 py-1.5 text-xs font-bold text-white">
                                    Tandai lunas
                                </button>
                            @endif
                            <button type="button" wire:click="edit({{ $bill->id }})" class="text-ink-soft"
                                    aria-label="Ubah {{ $bill->name }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 20h4l10-10a2.83 2.83 0 0 0-4-4L4 16z" />
                                </svg>
                            </button>
                            <button type="button" wire:click="archive({{ $bill->id }})"
                                    wire:confirm="Arsipkan &quot;{{ $bill->name }}&quot;? Riwayat pembayarannya tetap tersimpan."
                                    class="text-ink-soft" aria-label="Arsipkan {{ $bill->name }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 8h16M6 8v11a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V8M4 8V6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2" />
                                </svg>
                            </button>
                        </span>
                    </div>
                @endif
            </div>
        @empty
            <p class="card p-6 text-center text-sm text-ink-soft">
                Belum ada tagihan. Tekan tombol + untuk menambah yang pertama.
            </p>
        @endforelse
    </div>

    <div class="h-20" aria-hidden="true"></div>

    <x-fab label="Tambah tagihan" wire:click="toggleForm" />
</div>
