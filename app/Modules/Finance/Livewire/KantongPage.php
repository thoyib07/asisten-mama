<?php

namespace App\Modules\Finance\Livewire;

use App\Models\Household;
use App\Modules\Finance\Models\BudgetAllocation;
use App\Modules\Finance\Models\BudgetTopup;
use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Services\FinancePeriod;
use App\Modules\Finance\Services\Pockets;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class KantongPage extends Component
{
    /**
     * Tanggal mulai periode yang sedang dilihat. Datang dari `wire:model.live`, jadi selalu
     * dinormalkan lewat `periodStart()` sebelum dipakai membaca/menulis — bukan dipercaya mentah.
     */
    public string $periodStart = '';

    /** @var array<int, string> category_id => nominal alokasi periode ini */
    public array $amounts = [];

    /** Id `budget_allocations` yang sedang dibuka form top-up-nya. */
    public ?int $topupFor = null;

    public string $topupAmount = '';

    public ?int $editingCategoryId = null;

    public string $editName = '';

    public string $editIcon = '';

    public bool $adding = false;

    public string $newType = Category::TYPE_EXPENSE;

    public string $newName = '';

    public string $newIcon = '';

    public string $newAmount = '';

    public int $resetDay = FinancePeriod::MIN_RESET_DAY;

    public function mount(): void
    {
        $this->resetDay = $this->household()->budget_period_reset_day;
        $this->periodStart = FinancePeriod::startFor($this->resetDay)->toDateString();
        $this->loadAmounts();
    }

    public function updatedPeriodStart(): void
    {
        $this->reset(['topupFor', 'topupAmount']);
        $this->loadAmounts();
    }

    public function saveAllocations(): void
    {
        $this->validate([
            'amounts.*' => 'nullable|numeric|min:0',
        ], [
            'amounts.*.numeric' => 'Nominal kantong harus berupa angka.',
            'amounts.*.min' => 'Nominal kantong tidak boleh negatif.',
        ]);

        // Kunci array datang dari klien: kategori di luar household ini (atau yang sudah
        // diarsipkan) diabaikan, bukan dipercaya begitu saja ke updateOrCreate.
        $allowed = Category::active()->where('type', Category::TYPE_EXPENSE)->pluck('id')->all();

        foreach ($this->amounts as $categoryId => $amount) {
            if ($amount === '' || $amount === null || ! in_array((int) $categoryId, $allowed, true)) {
                continue;
            }

            BudgetAllocation::updateOrCreate(
                ['category_id' => (int) $categoryId, 'period_start' => $this->periodStart()],
                ['household_id' => $this->household()->id, 'amount' => $amount],
            );
        }

        $this->loadAmounts();
    }

    public function topup(): void
    {
        $this->validate([
            'topupFor' => 'required|integer',
            'topupAmount' => 'required|numeric|min:0.01',
        ], [
            'topupAmount.required' => 'Isi nominal tambahannya.',
            'topupAmount.min' => 'Nominal top-up harus lebih dari nol.',
        ]);

        $allocation = BudgetAllocation::findOrFail($this->topupFor);

        // Insert-only: nominal alokasi awal sengaja tidak diubah, supaya "rencana awal" dan
        // "berapa kali ditambah di tengah jalan" tetap bisa dibedakan nanti.
        BudgetTopup::create([
            'household_id' => $allocation->household_id,
            'budget_allocation_id' => $allocation->id,
            'user_id' => auth()->id(),
            'amount' => $this->topupAmount,
            'occurred_at' => now(),
        ]);

        $this->reset(['topupFor', 'topupAmount']);
    }

    public function addCategory(): void
    {
        $isExpense = $this->newType === Category::TYPE_EXPENSE;

        $this->validate([
            'newType' => 'required|in:income,expense',
            'newName' => 'required|string|max:50',
            'newIcon' => 'nullable|string|max:8',
            // Kantong baru tidak boleh lahir tanpa anggaran — tidak ada state "kantong ada tapi
            // belum pernah dianggarkan" untuk kategori buatan user.
            'newAmount' => $isExpense ? 'required|numeric|min:0' : 'nullable',
        ], [
            'newName.required' => 'Nama kantong belum diisi.',
            'newAmount.required' => 'Kantong baru wajib punya nominal anggaran awal.',
        ]);

        DB::transaction(function () use ($isExpense) {
            $category = Category::create([
                'household_id' => $this->household()->id,
                'type' => $this->newType,
                'name' => $this->newName,
                'icon' => $this->newIcon ?: null,
                'is_default' => false,
            ]);

            if ($isExpense) {
                BudgetAllocation::create([
                    'household_id' => $category->household_id,
                    'category_id' => $category->id,
                    'period_start' => $this->periodStart(),
                    'amount' => $this->newAmount,
                ]);
            }
        });

        $this->reset(['adding', 'newName', 'newIcon', 'newAmount', 'newType']);
        $this->loadAmounts();
    }

    public function editCategory(int $id): void
    {
        $category = Category::findOrFail($id);

        $this->editingCategoryId = $category->id;
        $this->editName = $category->name;
        $this->editIcon = (string) $category->icon;
    }

    public function saveCategory(): void
    {
        $this->validate([
            'editName' => 'required|string|max:50',
            'editIcon' => 'nullable|string|max:8',
        ]);

        Category::findOrFail($this->editingCategoryId)->update([
            'name' => $this->editName,
            'icon' => $this->editIcon ?: null,
        ]);

        $this->reset(['editingCategoryId', 'editName', 'editIcon']);
    }

    public function archive(int $id): void
    {
        $category = Category::findOrFail($id);

        // 7 kategori seed selamanya ada. Tombolnya memang tidak dirender untuk mereka, tapi
        // penegakannya di sini — bukan di blade.
        abort_unless($category->canBeArchived(), 403);

        $category->update(['archived_at' => now()]);
        $this->loadAmounts();
    }

    public function unarchive(int $id): void
    {
        Category::findOrFail($id)->update(['archived_at' => null]);
        $this->loadAmounts();
    }

    public function saveResetDay(): void
    {
        $this->validate([
            'resetDay' => 'required|integer|min:'.FinancePeriod::MIN_RESET_DAY.'|max:'.FinancePeriod::MAX_RESET_DAY,
        ], [
            'resetDay.max' => 'Tanggal reset maksimal 28, supaya batas periode sama di tiap bulan.',
        ]);

        // Non-retroaktif: alokasi & periode yang sudah tercatat tidak disentuh, tanggal baru
        // baru berlaku untuk periode berikutnya.
        $this->household()->update(['budget_period_reset_day' => $this->resetDay]);

        // Tanggal reset baru mengubah irama periode: lompat ke periode berjalan menurut aturan
        // baru, supaya kolom nominal di layar tidak lagi menunjuk batas periode yang lama.
        $this->periodStart = FinancePeriod::startFor($this->resetDay)->toDateString();
        $this->loadAmounts();
    }

    public function render()
    {
        $start = $this->periodStart();

        return view('livewire.finance.kantong-page', [
            'periods' => Pockets::selectablePeriods($this->resetDay),
            'periodLabel' => FinancePeriod::label($start),
            'pockets' => Pockets::forPeriod($start),
            'incomeCategories' => Category::active()->where('type', Category::TYPE_INCOME)->orderBy('name')->get(),
            'archived' => Category::whereNotNull('archived_at')->orderBy('name')->get(),
        ]);
    }

    /** Selalu batas periode yang sah menurut tanggal reset household, bukan input mentah. */
    private function periodStart(): string
    {
        return FinancePeriod::startFor($this->resetDay, $this->periodStart)->toDateString();
    }

    private function loadAmounts(): void
    {
        $this->amounts = BudgetAllocation::where('period_start', $this->periodStart())
            ->pluck('amount', 'category_id')
            ->map(fn ($amount) => (string) $amount)
            ->all();
    }

    private function household(): Household
    {
        return auth()->user()->currentHousehold;
    }
}
