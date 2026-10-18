<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Livewire\Concerns\VoidsExpenses;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Expenses paid out in the period, and the ledger where they are recorded.
 * Voided expenses stay listed but leave every total.
 */
#[Title('Expenses Report')]
class Expenses extends ReportComponent
{
    use VoidsExpenses;

    #[Url]
    public string $category = '';

    public string $expenseDate = '';

    public string $expensePvNumber = '';

    public string $expenseCategory = '';

    public string $expenseDescription = '';

    public string $expenseQuantity = '';

    public string $expensePayee = '';

    public string $expenseMethod = '';

    public string $expenseAmount = '';

    public function canRecord(): bool
    {
        return Auth::user()?->can('manage-payments') ?? false;
    }

    public function openCreate(): void
    {
        abort_unless($this->canRecord(), 403);

        $this->reset(['expensePvNumber', 'expenseCategory', 'expenseDescription', 'expenseQuantity', 'expensePayee', 'expenseMethod', 'expenseAmount']);
        $this->expenseDate = CarbonImmutable::today()->toDateString();
        $this->resetValidation();
        Flux::modal('expense-form')->show();
    }

    public function save(): void
    {
        abort_unless($this->canRecord(), 403);

        $validated = $this->validate([
            'expenseDate' => ['required', 'date', 'before_or_equal:today'],
            'expensePvNumber' => ['nullable', 'string', 'max:50'],
            'expenseCategory' => ['required', Rule::in($this->expenseCategories()->pluck('name')->all())],
            'expenseDescription' => ['required', 'string', 'max:255'],
            'expenseQuantity' => ['nullable', 'numeric', 'gt:0'],
            'expensePayee' => ['nullable', 'string', 'max:120'],
            'expenseMethod' => ['nullable', Rule::in($this->paymentMethods()->pluck('code')->all())],
            'expenseAmount' => ['required', 'numeric', 'gt:0'],
        ]);

        $currency = $this->hotelCurrency();

        Expense::query()->create([
            'recorded_by' => Auth::id(),
            'expense_date' => $validated['expenseDate'],
            'pv_number' => $validated['expensePvNumber'] ?: null,
            'category' => $validated['expenseCategory'],
            'description' => $validated['expenseDescription'],
            'quantity' => $validated['expenseQuantity'] !== '' ? $validated['expenseQuantity'] : null,
            'payee' => $validated['expensePayee'] ?: null,
            'method_code' => $validated['expenseMethod'] ?: null,
            'currency' => $currency,
            'amount' => Money::fromMajor((float) $validated['expenseAmount'], $currency)->amount,
        ]);

        Flux::modal('expense-form')->close();
        Flux::toast(variant: 'success', text: __('Expense recorded.'));
        $this->forgetExpenses();
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    #[Computed]
    public function expenseCategories(): Collection
    {
        return ExpenseCategory::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    #[Computed]
    public function paymentMethods(): Collection
    {
        return PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * @return Collection<int, Expense>
     */
    #[Computed]
    public function expenses(): Collection
    {
        return Expense::query()
            ->with(['recorder', 'voider'])
            ->whereDate('expense_date', '>=', $this->periodStart())
            ->whereDate('expense_date', '<=', $this->periodEnd())
            ->when($this->category !== '', fn (Builder $query) => $query->where('category', $this->category))
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, array{count: int, total: Money}>
     */
    #[Computed]
    public function totalsByCategory(): array
    {
        $totals = [];

        foreach ($this->activeExpenses() as $expense) {
            $totals[$expense->category] ??= ['count' => 0, 'total' => Money::zero($expense->currency)];
            $totals[$expense->category]['count']++;
            $totals[$expense->category]['total'] = $totals[$expense->category]['total']->add($expense->money());
        }

        uasort($totals, fn (array $a, array $b): int => $b['total']->amount <=> $a['total']->amount);

        return $totals;
    }

    #[Computed]
    public function grandTotal(): Money
    {
        return new Money((int) $this->activeExpenses()->sum('amount'), $this->hotelCurrency());
    }

    /**
     * The period's expenses that count towards the totals.
     *
     * @return Collection<int, Expense>
     */
    public function activeExpenses(): Collection
    {
        return $this->expenses()->reject(fn (Expense $expense): bool => $expense->isVoided());
    }

    public function render(): View
    {
        return view('livewire.reports.expenses');
    }

    protected function forgetExpenses(): void
    {
        unset($this->expenses, $this->totalsByCategory, $this->grandTotal);
    }

    protected function reportKey(): string
    {
        return 'expenses';
    }
}
