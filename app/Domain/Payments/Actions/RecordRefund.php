<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pays a guest back the credit on their folio — what they paid beyond what
 * they owe, e.g. after an early check-out credit on a prepaid stay.
 *
 * The refund is a confirmed payment with a negative amount, so it nets off
 * the folio's paid total and the cash-up for its method and day.
 */
final class RecordRefund
{
    public function execute(
        Folio $folio,
        string $methodCode,
        Money $amount,
        ?string $reference = null,
        ?User $refundedBy = null,
    ): Payment {
        if (! $amount->isPositive()) {
            throw new RuntimeException('Refund amount must be positive.');
        }

        return DB::transaction(function () use ($folio, $methodCode, $amount, $reference, $refundedBy): Payment {
            $folio = Folio::query()->lockForUpdate()->findOrFail($folio->id);

            if ($folio->status === 'void') {
                throw new RuntimeException('Cannot refund a voided folio.');
            }

            $credit = $folio->paid_total->subtract($folio->total_amount);

            if ($amount->amount > $credit->amount) {
                throw new RuntimeException("Only {$credit->format()} can be refunded on this folio.");
            }

            $payment = Payment::query()->create([
                'folio_id' => $folio->id,
                'method_code' => $methodCode,
                'amount' => Money::zero($amount->currency)->subtract($amount),
                'currency' => $amount->currency,
                'reference' => $reference,
                'status' => 'confirmed',
                'received_by' => $refundedBy?->id,
                'confirmed_at' => now(),
            ]);

            $folio->update(['paid_total' => $folio->paid_total->subtract($amount)]);

            return $payment;
        });
    }
}
