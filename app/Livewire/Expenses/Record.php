<?php

declare(strict_types=1);

namespace App\Livewire\Expenses;

use App\Domain\Tenancy\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Records (or corrects) one day-to-day expense, e.g. transport or supplies.
 */
#[Title('Add Daily Expenses')]
class Record extends Component
{
    public ?Expense $expense = null;

    public string $expenseDate = '';

    public string $pvNumber = '';

    public string $category = '';

    public string $description = '';

    public string $quantity = '';

    public string $payee = '';

    public string $method = '';

    public string $amount = '';

    public function mount(?Expense $expense = null): void
    {
        $this->authorizeManagement();

        if ($expense?->exists) {
            abort_if($expense->isVoided(), 403, __('A voided expense cannot be edited.'));

            $this->expense = $expense;
            $this->expenseDate = $expense->expense_date->toDateString();
            $this->pvNumber = $expense->pv_number ?? '';
            $this->category = $expense->category;
            $this->description = $expense->description;
            $this->quantity = $expense->quantity !== null ? rtrim(rtrim($expense->quantity, '0'), '.') : '';
            $this->payee = $expense->payee ?? '';
            $this->method = $expense->method_code ?? '';
            $this->amount = (string) $expense->money()->toMajor();

            return;
        }

        $this->expenseDate = CarbonImmutable::today()->toDateString();
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    #[Computed]
    public function categories(): Collection
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

    public function currency(): string
    {
        return $this->expense->currency ?? app(TenantContext::class)->current()->currency;
    }

    public function save(): void
    {
        $this->authorizeManagement();

        $validated = $this->validate([
            'expenseDate' => ['required', 'date', 'before_or_equal:today'],
            'pvNumber' => ['nullable', 'string', 'max:50'],
            'category' => ['required', Rule::in($this->categories()->pluck('name')->all())],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'payee' => ['nullable', 'string', 'max:120'],
            'method' => ['nullable', Rule::in($this->paymentMethods()->pluck('code')->all())],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $currency = $this->currency();

        $attributes = [
            'expense_date' => $validated['expenseDate'],
            'pv_number' => $validated['pvNumber'] ?: null,
            'category' => $validated['category'],
            'description' => $validated['description'],
            'quantity' => $validated['quantity'] !== '' ? $validated['quantity'] : null,
            'payee' => $validated['payee'] ?: null,
            'method_code' => $validated['method'] ?: null,
            'currency' => $currency,
            'amount' => Money::fromMajor((float) $validated['amount'], $currency)->amount,
        ];

        if ($this->expense) {
            abort_if($this->expense->fresh()?->isVoided() ?? true, 403, __('A voided expense cannot be edited.'));

            $this->expense->update($attributes);
            Flux::toast(variant: 'success', text: __('Expense updated.'));
        } else {
            Expense::query()->create(['recorded_by' => Auth::id(), ...$attributes]);
            Flux::toast(variant: 'success', text: __('Expense recorded.'));
        }

        $this->redirectRoute('expenses.list', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.expenses.record');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-payments') ?? false, 403);
    }
}
