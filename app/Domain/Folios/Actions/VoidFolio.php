<?php

declare(strict_types=1);

namespace App\Domain\Folios\Actions;

use App\Models\Folio;
use App\Models\PosOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancels a folio opened by mistake. Its charges drop out of the reports, and
 * POS orders billed to it are voided with their stock returned. Money must be
 * settled first: a folio holding payments cannot be voided until they are
 * voided or refunded.
 */
final class VoidFolio
{
    public function execute(Folio $folio, string $reason, ?User $actor = null): Folio
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Give a reason for voiding the folio.');
        }

        return DB::transaction(function () use ($folio, $reason, $actor): Folio {
            $folio = Folio::query()->lockForUpdate()->findOrFail($folio->id);
            $folio->load('stay');

            if ($folio->status === 'void') {
                throw new RuntimeException('This folio is already voided.');
            }

            if (! $folio->paid_total->isZero()) {
                throw new RuntimeException('This folio holds payments; void or refund them first.');
            }

            if ($folio->stay !== null && $folio->stay->checked_out_at === null) {
                throw new RuntimeException('The guest is still in-house; check them out first.');
            }

            PosOrder::query()
                ->where('folio_id', $folio->id)
                ->where('status', 'billed')
                ->get()
                ->each(function (PosOrder $order) use ($reason, $actor): void {
                    $order->restoreStock();
                    $order->update([
                        'status' => 'voided',
                        'voided_at' => now(),
                        'voided_by' => $actor?->id,
                        'void_reason' => __('Folio voided: :reason', ['reason' => trim($reason)]),
                    ]);
                });

            $folio->update([
                'status' => 'void',
                'closed_at' => $folio->closed_at ?? now(),
                'settled_at' => null,
                'voided_at' => now(),
                'voided_by' => $actor?->id,
                'void_reason' => trim($reason),
            ]);

            $folio->supersedeReceipts();

            return $folio;
        });
    }
}
