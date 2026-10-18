<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domain\Expenses\Actions\VoidExpense;
use App\Models\Expense;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Voids an expense from a list, asking for the reason in the
 * "void-expense" modal (livewire.expenses.partials.void-modal).
 */
trait VoidsExpenses
{
    public ?int $voidingExpenseId = null;

    public string $voidReason = '';

    /**
     * Clears the component's cached expense lists after a void.
     */
    abstract protected function forgetExpenses(): void;

    public function canVoidExpenses(): bool
    {
        return Auth::user()?->can('manage-payments') ?? false;
    }

    public function startVoidExpense(int $id): void
    {
        abort_unless($this->canVoidExpenses(), 403);

        $this->voidingExpenseId = Expense::query()->unvoided()->findOrFail($id)->id;
        $this->voidReason = '';
        $this->resetErrorBag('voidReason');
        Flux::modal('void-expense')->show();
    }

    public function voidExpense(VoidExpense $action): void
    {
        abort_unless($this->canVoidExpenses(), 403);

        $this->validate(['voidReason' => ['required', 'string', 'max:255']]);

        try {
            $action->execute(Expense::query()->findOrFail($this->voidingExpenseId), $this->voidReason, Auth::user());
        } catch (RuntimeException $e) {
            $this->addError('voidReason', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Expense voided.'));
        Flux::modal('void-expense')->close();
        $this->voidingExpenseId = null;
        $this->forgetExpenses();
    }
}
