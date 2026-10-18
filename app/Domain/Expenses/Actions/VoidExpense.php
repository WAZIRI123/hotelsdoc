<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Actions;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancels an expense recorded by mistake. It stays on record with who voided
 * it and why, but drops out of the expenses report, cash-up and profit & loss.
 */
final class VoidExpense
{
    public function execute(Expense $expense, string $reason, ?User $actor = null): Expense
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Give a reason for voiding the expense.');
        }

        return DB::transaction(function () use ($expense, $reason, $actor): Expense {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            if ($expense->isVoided()) {
                throw new RuntimeException('This expense is already voided.');
            }

            $expense->update([
                'voided_at' => now(),
                'voided_by' => $actor?->id,
                'void_reason' => trim($reason),
            ]);

            return $expense;
        });
    }
}
