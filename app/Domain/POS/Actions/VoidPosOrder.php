<?php

declare(strict_types=1);

namespace App\Domain\POS\Actions;

use App\Domain\Folios\Actions\PostFolioCharge;
use App\Models\PosOrder;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancels a POS order that was already billed: its charge is credited back
 * on the folio it went to, and its items' stock is returned. If the order
 * had been paid, the folio is left in credit for a refund.
 */
final class VoidPosOrder
{
    public function __construct(private readonly PostFolioCharge $postCharge) {}

    public function execute(PosOrder $order, string $reason, ?User $actor = null): PosOrder
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Give a reason for voiding the order.');
        }

        return DB::transaction(function () use ($order, $reason, $actor): PosOrder {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);
            $order->load('outlet', 'folio', 'items.menuItem');

            if ($order->status !== 'billed') {
                throw new RuntimeException("Cannot void a POS order in status [{$order->status}].");
            }

            $folio = $order->folio;

            if ($folio !== null && $folio->acceptsCharges()) {
                $this->postCharge->amounts(
                    folio: $folio,
                    sourceType: 'pos_order',
                    sourceId: $order->id,
                    description: "Void — {$order->outlet->name} order {$order->reference}",
                    net: Money::zero($order->subtotal->currency)->subtract($order->subtotal),
                    tax: Money::zero($order->tax_total->currency)->subtract($order->tax_total),
                );
            }

            $order->restoreStock();

            $order->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $actor?->id,
                'void_reason' => trim($reason),
            ]);

            return $order;
        });
    }
}
