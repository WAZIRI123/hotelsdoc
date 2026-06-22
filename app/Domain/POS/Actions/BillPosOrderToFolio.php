<?php

declare(strict_types=1);

namespace App\Domain\POS\Actions;

use App\Models\Folio;
use App\Models\PosOrder;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Posts a POS order's total as a charge on a folio.
 *
 * Default behaviour is "one summary charge per order" (cleaner folio view).
 * If you need per-line detail on the folio, switch to itemised mode by
 * configuring this action — the pos_order_items rows are always preserved
 * either way.
 *
 * Used both for bill-to-room (target = in-house guest's open folio) and
 * for walk-in POS settlements (target = freshly-created walk-in folio).
 */
final class BillPosOrderToFolio
{
    public function execute(PosOrder $order, Folio $folio): PosOrder
    {
        return DB::transaction(function () use ($order, $folio): PosOrder {
            if (! in_array($order->status, ['open', 'sent'], true)) {
                throw new RuntimeException(
                    "Cannot bill a POS order in status [{$order->status}].",
                );
            }

            if (! in_array($folio->status, ['open', 'closed'], true)) {
                throw new RuntimeException(
                    "Cannot bill onto a folio in status [{$folio->status}].",
                );
            }

            if ($order->hotel_id !== $folio->hotel_id) {
                throw new RuntimeException('Order and folio belong to different hotels.');
            }

            $order->loadMissing('items', 'outlet');

            if ($order->items->isEmpty()) {
                throw new RuntimeException('Cannot bill an empty order.');
            }

            $folio->charges()->create([
                'source_type' => 'pos_order',
                'source_id' => $order->id,
                'description' => "{$order->outlet->name} — order {$order->reference}",
                'quantity' => 1,
                'unit_price' => $order->total_amount,
                'tax_amount' => $order->tax_total,
                'total' => $order->total_amount,
                'posted_at' => now(),
            ]);

            $folio->refresh();
            $newSubtotal = $folio->subtotal->add($order->subtotal);
            $newTax = $folio->tax_total->add($order->tax_total);
            $newTotal = $newSubtotal->add($newTax);

            $folio->update([
                'subtotal' => $newSubtotal,
                'tax_total' => $newTax,
                'total_amount' => $newTotal,
            ]);

            $order->update([
                'folio_id' => $folio->id,
                'status' => 'billed',
                'billed_at' => now(),
            ]);

            return $order->fresh() ?? $order;
        });
    }

    /**
     * Convenience: settle a walk-in POS order by creating a fresh walk-in folio.
     *
     * Used when the customer is paying at the counter, not bill-to-room.
     */
    public function settleAsWalkIn(PosOrder $order): Folio
    {
        return DB::transaction(function () use ($order): Folio {
            $folio = Folio::query()->create([
                'hotel_id' => $order->hotel_id,
                'status' => 'open',
                'subtotal' => Money::zero(),
                'tax_total' => Money::zero(),
                'total_amount' => Money::zero(),
            ]);

            $this->execute($order, $folio);

            return $folio->fresh() ?? $folio;
        });
    }
}
