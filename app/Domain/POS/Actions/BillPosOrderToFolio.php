<?php

declare(strict_types=1);

namespace App\Domain\POS\Actions;

use App\Domain\Folios\Actions\PostFolioCharge;
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
    public function __construct(private readonly PostFolioCharge $postCharge) {}

    public function execute(PosOrder $order, Folio $folio): PosOrder
    {
        return DB::transaction(function () use ($order, $folio): PosOrder {
            if ($order->status !== 'open') {
                throw new RuntimeException(
                    "Cannot bill a POS order in status [{$order->status}].",
                );
            }

            if (! $folio->acceptsCharges()) {
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

            $this->postCharge->amounts(
                folio: $folio,
                sourceType: 'pos_order',
                sourceId: $order->id,
                description: "{$order->outlet->name} — order {$order->reference}",
                net: $order->subtotal,
                tax: $order->tax_total,
            );

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
