<?php

use App\Modules\Finance\Livewire\KantongPage;
use App\Modules\Finance\Models\BudgetAllocation;
use App\Modules\Finance\Models\BudgetTopup;
use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Services\FinancePeriod;
use App\Modules\Finance\Services\Pockets;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = makeHouseholdUser('Rina');
    auth()->login($this->user);
    $this->periodStart = FinancePeriod::startFor(1)->toDateString();
    $this->belanja = Category::where('name', 'Belanja Harian')->first();
});

function recordExpense(int $categoryId, float $amount, ?string $on = null): Transaction
{
    return Transaction::create([
        'household_id' => auth()->user()->current_household_id,
        'category_id' => $categoryId,
        'user_id' => auth()->id(),
        'type' => 'expense',
        'amount' => $amount,
        'occurred_on' => $on ?? now()->toDateString(),
    ]);
}

it('menyimpan alokasi per kantong untuk periode berjalan', function () {
    Livewire::test(KantongPage::class)
        ->set('amounts.'.$this->belanja->id, '1500000')
        ->call('saveAllocations')
        ->assertHasNoErrors();

    expect(BudgetAllocation::where('category_id', $this->belanja->id)->first())
        ->period_start->toDateString()->toBe($this->periodStart)
        ->amount->toBe('1500000.00');
});

it('memperbarui alokasi yang sudah ada, bukan membuat baris kedua', function () {
    Livewire::test(KantongPage::class)
        ->set('amounts.'.$this->belanja->id, '1000000')
        ->call('saveAllocations')
        ->set('amounts.'.$this->belanja->id, '1200000')
        ->call('saveAllocations');

    expect(BudgetAllocation::count())->toBe(1);
    expect(BudgetAllocation::first()->amount)->toBe('1200000.00');
});

it('mengabaikan kategori milik household lain di array alokasi', function () {
    $budi = makeHouseholdUser('Budi');
    auth()->login($budi);
    $budiCategory = Category::where('type', 'expense')->first();

    auth()->login($this->user);

    Livewire::test(KantongPage::class)
        ->set('amounts.'.$budiCategory->id, '999999')
        ->call('saveAllocations');

    expect(BudgetAllocation::withoutGlobalScope('household')->count())->toBe(0);
});

it('menolak mengarsipkan kategori default', function () {
    Livewire::test(KantongPage::class)
        ->call('archive', $this->belanja->id)
        ->assertForbidden();

    expect($this->belanja->fresh()->archived_at)->toBeNull();
});

it('mengarsipkan kantong custom tanpa menghapus riwayatnya', function () {
    $custom = Livewire::test(KantongPage::class)
        ->set('newType', 'expense')
        ->set('newName', 'Kebutuhan Bayi')
        ->set('newAmount', '500000')
        ->call('addCategory')
        ->assertHasNoErrors();

    $category = Category::where('name', 'Kebutuhan Bayi')->firstOrFail();
    recordExpense($category->id, 120000);

    $custom->call('archive', $category->id)->assertHasNoErrors();

    expect($category->fresh()->archived_at)->not->toBeNull();
    expect(Transaction::where('category_id', $category->id)->count())->toBe(1);
    expect(Pockets::forPeriod($this->periodStart)->pluck('category.id'))->not->toContain($category->id);
});

it('mewajibkan nominal anggaran awal saat membuat kantong pengeluaran', function () {
    Livewire::test(KantongPage::class)
        ->set('newType', 'expense')
        ->set('newName', 'Kebutuhan Bayi')
        ->set('newAmount', '')
        ->call('addCategory')
        ->assertHasErrors(['newAmount' => 'required']);

    expect(Category::where('name', 'Kebutuhan Bayi')->exists())->toBeFalse();
});

it('membuat kategori pemasukan custom tanpa anggaran', function () {
    Livewire::test(KantongPage::class)
        ->set('newType', 'income')
        ->set('newName', 'Bonus')
        ->call('addCategory')
        ->assertHasNoErrors();

    expect(Category::where('name', 'Bonus')->first())
        ->is_default->toBeFalse();
    expect(BudgetAllocation::count())->toBe(0);
});

it('menambah budget lewat top-up tanpa mengubah alokasi awal', function () {
    Livewire::test(KantongPage::class)
        ->set('amounts.'.$this->belanja->id, '1000000')
        ->call('saveAllocations');

    $allocation = BudgetAllocation::firstOrFail();

    Livewire::test(KantongPage::class)
        ->set('topupFor', $allocation->id)
        ->set('topupAmount', '250000')
        ->call('topup')
        ->assertHasNoErrors()
        ->assertSet('topupFor', null);

    expect($allocation->fresh()->amount)->toBe('1000000.00');
    expect(BudgetTopup::count())->toBe(1);

    $pocket = Pockets::forPeriod($this->periodStart)->firstWhere('category.id', $this->belanja->id);
    expect($pocket['budget'])->toBe(1250000.0);
    expect($pocket['remaining'])->toBe(1250000.0);
});

it('menghitung sisa kantong dari transaksi nyata, termasuk setelah diedit dan dihapus', function () {
    Livewire::test(KantongPage::class)
        ->set('amounts.'.$this->belanja->id, '1000000')
        ->call('saveAllocations');

    $transaction = recordExpense($this->belanja->id, 300000);

    $sisa = fn () => Pockets::forPeriod($this->periodStart)
        ->firstWhere('category.id', $this->belanja->id)['remaining'];

    expect($sisa())->toBe(700000.0);

    $transaction->update(['amount' => 400000]);
    expect($sisa())->toBe(600000.0);

    // Pindah periode: pengeluarannya keluar dari hitungan periode ini.
    $transaction->update(['occurred_on' => now()->subMonthNoOverflow()->startOfMonth()->toDateString()]);
    expect($sisa())->toBe(1000000.0);

    $transaction->delete();
    expect($sisa())->toBe(1000000.0);
});

it('menampilkan kantong yang belum dianggarkan sebagai null, bukan nol', function () {
    recordExpense($this->belanja->id, 50000);

    $pocket = Pockets::forPeriod($this->periodStart)->firstWhere('category.id', $this->belanja->id);

    expect($pocket['budget'])->toBeNull();
    expect($pocket['remaining'])->toBeNull();
    expect($pocket['spent'])->toBe(50000.0);
});

it('membiarkan kantong minus saat pengeluaran melewati anggarannya', function () {
    Livewire::test(KantongPage::class)
        ->set('amounts.'.$this->belanja->id, '100000')
        ->call('saveAllocations');

    recordExpense($this->belanja->id, 150000);

    expect(Pockets::forPeriod($this->periodStart)->firstWhere('category.id', $this->belanja->id)['remaining'])
        ->toBe(-50000.0);
});

it('membatasi tanggal reset periode di 1 sampai 28', function () {
    Livewire::test(KantongPage::class)
        ->set('resetDay', 30)
        ->call('saveResetDay')
        ->assertHasErrors(['resetDay']);

    Livewire::test(KantongPage::class)
        ->set('resetDay', 25)
        ->call('saveResetDay')
        ->assertHasNoErrors();

    expect($this->user->currentHousehold->fresh()->budget_period_reset_day)->toBe(25);
});

it('menormalkan periodStart mentah dari klien ke batas periode yang sah', function () {
    Livewire::test(KantongPage::class)
        ->set('periodStart', '2026-08-13')
        ->set('amounts.'.$this->belanja->id, '100000')
        ->call('saveAllocations');

    expect(BudgetAllocation::first()->period_start->toDateString())->toBe('2026-08-01');
});

it('menyimpan alokasi ke irama periode yang baru setelah tanggal reset diubah', function () {
    Livewire::test(KantongPage::class)
        ->set('resetDay', 25)
        ->call('saveResetDay')
        ->assertSet('periodStart', FinancePeriod::startFor(25)->toDateString())
        ->set('amounts.'.$this->belanja->id, '1500000')
        ->call('saveAllocations');

    expect(BudgetAllocation::first()->period_start->toDateString())
        ->toBe(FinancePeriod::startFor(25)->toDateString());
});

it('tetap bisa membuka periode dengan irama lama setelah tanggal reset diubah', function () {
    Livewire::test(KantongPage::class)
        ->set('amounts.'.$this->belanja->id, '1000000')
        ->call('saveAllocations');

    Livewire::test(KantongPage::class)->set('resetDay', 25)->call('saveResetDay');

    expect(Pockets::selectablePeriods(25)->pluck('value'))->toContain($this->periodStart);
});
