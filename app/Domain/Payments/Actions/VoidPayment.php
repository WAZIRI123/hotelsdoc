<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Models\Folio;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancels a payment (or refund) recorded by mistake. It drops out of the
 * cash-up and the folio's paid total; a folio that is no longer fully paid
 * reopens and its fiscal receipt is superseded.
 */
final class VoidPayment
{
    public function execute(Payment $payment, string $reason, ?User $actor = null): Payment
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Give a reason for voiding the payment.');
        }

        return DB::transaction(function () use ($payment, $reason, $actor): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'confirmed') {
                throw new RuntimeException("Cannot void a payment in status [{$payment->status}].");
            }

            $folio = Folio::query()->lockForUpdate()->findOrFail($payment->folio_id);

            if ($folio->status === 'void') {
                throw new RuntimeException('Cannot void a payment on a voided guest billing.');
            }

            $payment->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $actor?->id,
                'void_reason' => trim($reason),
            ]);

            $paid = $folio->paid_total->subtract($payment->amount);
            $reopen = $folio->status === 'settled' && $paid->amount < $folio->total_amount->amount;

            $folio->update([
                'paid_total' => $paid,
                'status' => $reopen ? $folio->unsettledStatus() : $folio->status,
                'settled_at' => $reopen ? null : $folio->settled_at,
            ]);

            if ($reopen) {
                $folio->supersedeReceipts();
            }

            return $payment;
        });
    }
}
