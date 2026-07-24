<?php

namespace App\Modules\Finance\Livewire;

use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class FinancePage extends Component
{
    public string $type = Transaction::TYPE_EXPENSE;

    public ?int $categoryId = null;

    public string $amount = '';

    public string $description = '';

    public string $occurredOn = '';

    public function mount(): void
    {
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
            'categoryId' => 'nullable|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'occurredOn' => 'required|date',
        ]);

        Transaction::create([
            'household_id' => auth()->user()->current_household_id,
            'category_id' => $this->categoryId,
            'user_id' => auth()->id(),
            'type' => $this->type,
            'amount' => $this->amount,
            'description' => $this->description,
            'occurred_on' => $this->occurredOn,
        ]);

        $this->reset(['amount', 'description', 'categoryId']);
        $this->occurredOn = now()->toDateString();
    }

    public function render()
    {
        $categories = Category::where('type', $this->type)->orderBy('name')->get();

        $transactions = Transaction::with('category')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->take(20)
            ->get();

        $monthlyIncome = (float) Transaction::income()
            ->whereBetween('occurred_on', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $monthlyExpense = (float) Transaction::expense()
            ->whereBetween('occurred_on', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        return view('livewire.finance.finance-page', [
            'categories' => $categories,
            'transactions' => $transactions,
            'monthlyIncome' => $monthlyIncome,
            'monthlyExpense' => $monthlyExpense,
            'monthlyBalance' => $monthlyIncome - $monthlyExpense,
        ]);
    }
}
