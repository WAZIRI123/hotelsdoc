<?php

declare(strict_types=1);

namespace App\Livewire\Expenses;

use App\Domain\Tenancy\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Lists the day-to-day expenses recorded in a date range.
 */
#[Title('View Daily Expenses')]
class Index extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $category = '';

    public function mount(): void
    {
        $this->authorizeManagement();

        $today = CarbonImmutable::today();
        $this->from = $this->validDate($this->from) ?? $today->startOfMonth()->toDateString();
        $this->to = $this->validDate($this->to) ?? $today->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to'], true)) {
            $this->{$property} = $this->validDate($this->{$property}) ?? CarbonImmutable::today()->toDateString();
        }

        unset($this->expenses);
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
     * @return Collection<int, Expense>
     */
    #[Computed]
    public function expenses(): Collection
    {
        return Expense::query()
            ->with('recorder')
            ->whereDate('expense_date', '>=', $this->from)
            ->whereDate('expense_date', '<=', $this->to)
            ->when($this->category !== '', fn (Builder $query) => $query->where('category', $this->category))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function total(): Money
    {
        return new Money((int) $this->expenses()->sum('amount'), app(TenantContext::class)->current()->currency);
    }

    public function delete(int $id): void
    {
        $this->authorizeManagement();

        Expense::query()->findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('Expense deleted.'));
        unset($this->expenses);
    }

    public function render(): View
    {
        return view('livewire.expenses.index');
    }

    private function validDate(string $value): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return rescue(fn (): string => CarbonImmutable::createFromFormat('!Y-m-d', $value)->toDateString(), null, false);
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-payments') ?? false, 403);
    }
}
