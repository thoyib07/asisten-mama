<?php

namespace App\Modules\Bills\Livewire;

use App\Modules\Bills\Models\Bill;
use App\Modules\Bills\Services\BillSchedule;
use App\Modules\Bills\Services\RecordBillPayment;
use App\Modules\Finance\Models\Category;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RRule\RRule;
use RuntimeException;
use Throwable;

#[Layout('components.layout')]
class BillList extends Component
{
    /** Preset pengulangan. Nilai RRULE-nya diturunkan dari tanggal jatuh tempo pertama. */
    public const REPEATS = [
        'sekali' => 'Sekali jalan',
        'bulanan' => 'Tiap bulan',
        'triwulan' => 'Tiap 3 bulan',
        'tahunan' => 'Tiap tahun',
        'mingguan' => 'Tiap minggu',
        'lanjutan' => 'Lanjutan…',
    ];

    public bool $adding = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $amountEstimate = '';

    public ?int $categoryId = null;

    public string $startsOn = '';

    public string $repeat = 'bulanan';

    public string $customRrule = '';

    public int $reminderDaysBefore = 2;

    public string $notes = '';

    /** Tagihan yang sedang ditandai lunas, beserta periode & nominal aslinya. */
    public ?int $payingBillId = null;

    public string $payingPeriod = '';

    public string $payAmount = '';

    public bool $showConnect = false;

    public function mount(): void
    {
        $this->startsOn = now()->toDateString();
    }

    public function toggleForm(): void
    {
        $this->adding = ! $this->adding;

        if ($this->adding) {
            $this->resetForm();
        }
    }

    public function edit(int $billId): void
    {
        $bill = Bill::findOrFail($billId);

        $this->editingId = $bill->id;
        $this->adding = true;
        $this->name = $bill->name;
        $this->amountEstimate = $bill->amount_estimate === null ? '' : (string) (float) $bill->amount_estimate;
        $this->categoryId = $bill->category_id;
        $this->startsOn = $bill->starts_on->toDateString();
        $this->reminderDaysBefore = $bill->reminder_days_before;
        $this->notes = (string) $bill->notes;

        // Preset tidak disimpan di DB — cuma `rrule`-nya. Kalau string-nya bukan salah satu
        // preset yang kita hasilkan sendiri, tampilkan sebagai "Lanjutan" apa adanya.
        $this->repeat = $this->presetFor($bill);
        $this->customRrule = $this->repeat === 'lanjutan' ? (string) $bill->rrule : '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'amountEstimate' => ['nullable', 'numeric', 'min:0'],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'startsOn' => ['required', 'date'],
            'repeat' => ['required', 'in:'.implode(',', array_keys(self::REPEATS))],
            'reminderDaysBefore' => ['required', 'integer', 'min:0', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'customRrule' => ['nullable', 'string', 'max:255', $this->validRruleRule()],
        ]);

        $attributes = [
            'name' => $data['name'],
            'amount_estimate' => $data['amountEstimate'] === '' ? null : $data['amountEstimate'],
            'category_id' => $data['categoryId'],
            'starts_on' => $data['startsOn'],
            'rrule' => $this->rruleFromForm(),
            'reminder_days_before' => $data['reminderDaysBefore'],
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->editingId) {
            Bill::findOrFail($this->editingId)->update($attributes);
        } else {
            Bill::create($attributes);
        }

        $this->resetForm();
        $this->adding = false;
    }

    public function startPaying(int $billId, string $period): void
    {
        $bill = Bill::findOrFail($billId);

        $this->payingBillId = $bill->id;
        $this->payingPeriod = $period;
        // Perkiraan cuma jadi nilai awal — user mengganti dengan nominal tagihan yang asli.
        $this->payAmount = $bill->amount_estimate === null ? '' : (string) (float) $bill->amount_estimate;
    }

    public function cancelPaying(): void
    {
        $this->reset('payingBillId', 'payingPeriod', 'payAmount');
    }

    public function confirmPaying(RecordBillPayment $recorder, BillSchedule $schedule): void
    {
        $this->validate([
            'payAmount' => ['required', 'numeric', 'min:0.01'],
            'payingPeriod' => ['required', 'date'],
        ], attributes: ['payAmount' => 'nominal']);

        $bill = Bill::findOrFail($this->payingBillId);

        // payingPeriod adalah properti Livewire publik: nilainya datang dari klien, tombol di
        // blade cuma mengusulkan. Tanpa cek ini, pembayaran bisa dicatat pada tanggal yang
        // bukan jatuh tempo — dan kalau tanggalnya kebetulan cocok dengan occurrence di masa
        // depan, occurrence itu diam-diam hilang dari kalender.
        if (! $schedule->isOccurrence($bill, $this->payingPeriod)) {
            $this->addError('payAmount', 'Tanggal itu bukan jatuh tempo tagihan ini.');

            return;
        }

        try {
            $recorder->record(
                $bill,
                $this->payingPeriod,
                (float) $this->payAmount,
                auth()->user(),
            );
        } catch (RuntimeException $e) {
            $this->addError('payAmount', $e->getMessage());

            return;
        }

        $this->cancelPaying();
    }

    public function archive(int $billId): void
    {
        Bill::findOrFail($billId)->forceFill(['archived_at' => now()])->save();
    }

    public function regenerateCalendarUrl(): void
    {
        auth()->user()->currentHousehold->regenerateCalendarToken();
    }

    private function resetForm(): void
    {
        $this->reset('name', 'amountEstimate', 'categoryId', 'repeat', 'customRrule', 'notes', 'editingId');
        $this->startsOn = now()->toDateString();
        $this->reminderDaysBefore = 2;
    }

    /**
     * Field "Lanjutan" diketik user dan ikut menentukan isi feed kalender. RRULE yang tidak
     * valid harus ditolak di sini — bukan diteruskan lalu meledak saat feed dibangun.
     */
    private function validRruleRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail) {
            if ($this->repeat !== 'lanjutan') {
                return;
            }

            if (blank($value)) {
                $fail('Isi aturan pengulangannya, atau pilih preset di atas.');

                return;
            }

            try {
                $rule = new RRule($value, Carbon::parse($this->startsOn));
            } catch (Throwable) {
                $fail('Aturan pengulangan tidak dikenali. Contoh: FREQ=MONTHLY;BYMONTHDAY=20');

                return;
            }

            if ($rule->getOccurrencesBetween($this->startsOn, Carbon::parse($this->startsOn)->addYears(5)) === []) {
                $fail('Aturan ini tidak menghasilkan tanggal jatuh tempo satu pun.');
            }
        };
    }

    private function rruleFromForm(?string $repeat = null): ?string
    {
        $date = Carbon::parse($this->startsOn);

        return match ($repeat ?? $this->repeat) {
            'sekali' => null,
            'bulanan' => "FREQ=MONTHLY;BYMONTHDAY={$date->day}",
            'triwulan' => "FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY={$date->day}",
            'tahunan' => "FREQ=YEARLY;BYMONTH={$date->month};BYMONTHDAY={$date->day}",
            'mingguan' => 'FREQ=WEEKLY;BYDAY='.strtoupper(substr($date->format('D'), 0, 2)),
            'lanjutan' => $this->customRrule,
        };
    }

    private function presetFor(Bill $bill): string
    {
        if (! $bill->rrule) {
            return 'sekali';
        }

        foreach (['bulanan', 'triwulan', 'tahunan', 'mingguan'] as $preset) {
            if ($this->rruleFromForm($preset) === $bill->rrule) {
                return $preset;
            }
        }

        return 'lanjutan';
    }

    public function render(BillSchedule $schedule)
    {
        $bills = Bill::with('category')->active()->orderBy('name')->get();

        // Satu occurrence berikutnya yang belum lunas per tagihan — inilah yang dilihat user
        // sebagai "yang harus dibayar", bukan seluruh riwayat pengulangannya.
        $due = $bills->mapWithKeys(fn (Bill $bill) => [
            $bill->id => $schedule->nextUnpaid($bill, BillSchedule::lookbackFrom()),
        ]);

        return view('livewire.bills.bill-list', [
            'bills' => $bills->sortBy(fn (Bill $bill) => $due[$bill->id] ?? '9999-12-31')->values(),
            'due' => $due,
            'categories' => Category::where('type', Category::TYPE_EXPENSE)->orderBy('name')->get(),
            'repeats' => self::REPEATS,
            'feedUrl' => route('tagihan.kalender', ['token' => auth()->user()->currentHousehold->calendarToken()]),
        ]);
    }
}
