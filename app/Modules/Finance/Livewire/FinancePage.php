<?php

namespace App\Modules\Finance\Livewire;

use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Services\FinancePeriod;
use App\Modules\Finance\Services\Pockets;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class FinancePage extends Component
{
    /** Tanggal mulai periode yang sedang difilter — ringkasan & riwayat memakai scope yang sama. */
    public string $periodStart = '';

    public string $type = Transaction::TYPE_EXPENSE;

    public ?int $categoryId = null;

    public string $amount = '';

    public string $description = '';

    public string $occurredOn = '';

    /** Diisi saat mengedit transaksi lama; null berarti form sedang membuat transaksi baru. */
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->periodStart = FinancePeriod::startFor($this->resetDay())->toDateString();
        $this->occurredOn = now()->toDateString();
    }

    public function updatedType(): void
    {
        $this->categoryId = null;
    }

    public function save(): void
    {
        $this->validate([
            'type' => 'required|in:income,expense',
            // Kategori wajib untuk pengeluaran: tiap pengeluaran harus jelas mengurangi kantong
            // yang mana. Pemasukan tidak kenal kantong, jadi tetap opsional.
            //
            // Rule::exists() bukan 'exists:categories,id' — rule bawaan menembak query mentah,
            // jadi global scope BelongsToHousehold tidak berlaku dan categoryId milik household
            // lain akan lolos. Transaksinya lalu memegang FK asing yang ikut ter-null saat
            // household itu menghapus kategorinya (nullOnDelete).
            'categoryId' => [
                $this->type === Transaction::TYPE_EXPENSE ? 'required' : 'nullable',
                Rule::exists('categories', 'id')
                    ->where('household_id', auth()->user()->current_household_id)
                    ->where('type', $this->type),
            ],
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'occurredOn' => 'required|date',
        ], [
            'categoryId.required' => 'Pilih kantong yang dipakai untuk pengeluaran ini.',
        ]);

        $values = [
            'category_id' => $this->categoryId,
            'type' => $this->type,
            'amount' => $this->amount,
            'description' => $this->description,
            'occurred_on' => $this->occurredOn,
        ];

        if ($this->editingId) {
            // findOrFail lewat global scope: id milik household lain berakhir 404, bukan ter-update.
            Transaction::findOrFail($this->editingId)->update($values);
        } else {
            Transaction::create($values + [
                'household_id' => auth()->user()->current_household_id,
                'user_id' => auth()->id(),
            ]);
        }

        // Ikut pindah ke periode transaksinya, supaya yang baru disimpan tidak "hilang" dari
        // riwayat cuma karena tanggalnya di luar periode yang sedang difilter.
        $this->periodStart = FinancePeriod::startFor($this->resetDay(), $this->occurredOn)->toDateString();

        $this->cancelEdit();
    }

    public function edit(int $id): void
    {
        $transaction = Transaction::findOrFail($id);

        $this->editingId = $transaction->id;
        $this->type = $transaction->type;
        $this->categoryId = $transaction->category_id;
        $this->amount = (string) $transaction->amount;
        $this->description = (string) $transaction->description;
        $this->occurredOn = $transaction->occurred_on->toDateString();
    }

    public function cancelEdit(): void
    {
        $this->reset(['amount', 'description', 'categoryId', 'editingId']);
        $this->occurredOn = now()->toDateString();
    }

    public function delete(int $id): void
    {
        Transaction::findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }

    public function render()
    {
        $start = FinancePeriod::startFor($this->resetDay(), $this->periodStart);
        $end = FinancePeriod::endOf($start);
        $range = [$start->toDateString(), $end->toDateString()];

        // Kategori yang sudah diarsipkan tidak muncul untuk transaksi baru, tapi tetap
        // ditampilkan saat transaksi lama yang memakainya sedang diedit.
        $categories = Category::where('type', $this->type)
            ->where(fn ($q) => $q->whereNull('archived_at')->orWhere('id', $this->categoryId))
            ->orderBy('name')
            ->get();

        $income = (float) Transaction::income()->whereBetween('occurred_on', $range)->sum('amount');
        $expense = (float) Transaction::expense()->whereBetween('occurred_on', $range)->sum('amount');

        return view('livewire.finance.finance-page', [
            'periods' => Pockets::selectablePeriods($this->resetDay()),
            'periodLabel' => FinancePeriod::label($start),
            'categories' => $categories,
            'remaining' => Pockets::forPeriod($start)
                ->mapWithKeys(fn (array $pocket) => [$pocket['category']->id => $pocket['remaining']]),
            'transactions' => Transaction::with('category')
                ->whereBetween('occurred_on', $range)
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->get(),
            'monthlyIncome' => $income,
            'monthlyExpense' => $expense,
            'monthlyBalance' => $income - $expense,
        ]);
    }

    private function resetDay(): int
    {
        return auth()->user()->currentHousehold?->budget_period_reset_day ?? FinancePeriod::MIN_RESET_DAY;
    }
}
