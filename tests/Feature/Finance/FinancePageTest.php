<?php

use App\Modules\Finance\Livewire\FinancePage;
use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Services\FinancePeriod;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = makeHouseholdUser('Rina');
    auth()->login($this->user);
    $this->expenseCategory = Category::where('type', Category::TYPE_EXPENSE)->first();
});

it('menolak pengeluaran yang tidak dipetakan ke kantong', function () {
    Livewire::test(FinancePage::class)
        ->set('type', 'expense')
        ->set('categoryId', null)
        ->set('amount', 50000)
        ->call('save')
        ->assertHasErrors(['categoryId' => 'required']);

    expect(Transaction::count())->toBe(0);
});

it('tetap menerima pemasukan tanpa kategori', function () {
    Livewire::test(FinancePage::class)
        ->set('type', 'income')
        ->set('categoryId', null)
        ->set('amount', 3_000_000)
        ->call('save')
        ->assertHasNoErrors();

    expect(Transaction::first()->category_id)->toBeNull();
});

it('menolak kategori yang tipenya tidak cocok dengan transaksinya', function () {
    $income = Category::where('type', Category::TYPE_INCOME)->first();

    Livewire::test(FinancePage::class)
        ->set('type', 'expense')
        ->set('categoryId', $income->id)
        ->set('amount', 50000)
        ->call('save')
        ->assertHasErrors(['categoryId']);
});

it('membatasi ringkasan dan riwayat ke periode yang sedang difilter', function () {
    $lastPeriod = now()->subMonthNoOverflow()->startOfMonth()->addDays(4);

    Transaction::create([
        'household_id' => $this->user->current_household_id,
        'category_id' => $this->expenseCategory->id,
        'user_id' => $this->user->id,
        'type' => 'expense',
        'amount' => 100000,
        'occurred_on' => $lastPeriod->toDateString(),
    ]);

    Transaction::create([
        'household_id' => $this->user->current_household_id,
        'category_id' => $this->expenseCategory->id,
        'user_id' => $this->user->id,
        'type' => 'expense',
        'amount' => 25000,
        'occurred_on' => now()->toDateString(),
    ]);

    $component = Livewire::test(FinancePage::class)
        ->assertSet('periodStart', now()->startOfMonth()->toDateString())
        ->assertViewHas('monthlyExpense', 25000.0);

    expect($component->viewData('transactions'))->toHaveCount(1);

    $component->set('periodStart', $lastPeriod->copy()->startOfMonth()->toDateString())
        ->assertViewHas('monthlyExpense', 100000.0);
});

it('ikut pindah ke periode transaksi yang baru disimpan', function () {
    $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(4);

    Livewire::test(FinancePage::class)
        ->set('categoryId', $this->expenseCategory->id)
        ->set('amount', 75000)
        ->set('occurredOn', $lastMonth->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('periodStart', FinancePeriod::startFor(1, $lastMonth)->toDateString());
});

it('bisa mengubah transaksi yang sudah dicatat', function () {
    $transaction = Transaction::create([
        'household_id' => $this->user->current_household_id,
        'category_id' => $this->expenseCategory->id,
        'user_id' => $this->user->id,
        'type' => 'expense',
        'amount' => 50000,
        'occurred_on' => now()->toDateString(),
    ]);

    Livewire::test(FinancePage::class)
        ->call('edit', $transaction->id)
        ->assertSet('amount', '50000.00')
        ->set('amount', 65000)
        ->set('description', 'Koreksi struk')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect((float) $transaction->fresh()->amount)->toBe(65000.0);
    expect($transaction->fresh()->description)->toBe('Koreksi struk');
    expect(Transaction::count())->toBe(1);
});

it('bisa menghapus transaksi', function () {
    $transaction = Transaction::create([
        'household_id' => $this->user->current_household_id,
        'category_id' => $this->expenseCategory->id,
        'user_id' => $this->user->id,
        'type' => 'expense',
        'amount' => 50000,
        'occurred_on' => now()->toDateString(),
    ]);

    Livewire::test(FinancePage::class)->call('delete', $transaction->id);

    expect(Transaction::count())->toBe(0);
});

it('tidak bisa mengubah transaksi milik household lain', function () {
    $budi = makeHouseholdUser('Budi');
    auth()->login($budi);
    $budiTransaction = Transaction::create([
        'household_id' => $budi->current_household_id,
        'category_id' => Category::where('type', 'expense')->first()->id,
        'user_id' => $budi->id,
        'type' => 'expense',
        'amount' => 50000,
        'occurred_on' => now()->toDateString(),
    ]);

    auth()->login($this->user);

    // Global scope BelongsToHousehold membuat findOrFail tidak menemukannya sama sekali —
    // di request nyata ini jadi 404, bukan transaksi household lain yang ikut terhapus.
    expect(fn () => Livewire::test(FinancePage::class)->call('delete', $budiTransaction->id))
        ->toThrow(ModelNotFoundException::class);

    expect(Transaction::withoutGlobalScope('household')->count())->toBe(1);
});
