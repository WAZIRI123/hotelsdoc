<?php

declare(strict_types=1);

namespace App\Domain\POS\Actions;

use App\Models\PosOrder;
use App\Support\Money;

/**
 * Sums the order's line items into the order's subtotal/tax_total/total_amount.
 * Called whenever items change.
 */
final class RecalculatePosOrderTotals
{
    public function execute(PosOrder $order): PosOrder
    {
        // load() not loadMissing() — items may already be loaded but stale after a
        // sibling insert/update; we need the canonical DB state to sum totals.
        $order->load('items');

        $subtotal = Money::zero();
        $tax = Money::zero();

        foreach ($order->items as $item) {
            $subtotal = $subtotal->add($item->line_total);
            $tax = $tax->add($item->tax_amount);
        }

        $order->update([
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total_amount' => $subtotal->add($tax),
        ]);

        return $order->fresh() ?? $order;
    }
}
