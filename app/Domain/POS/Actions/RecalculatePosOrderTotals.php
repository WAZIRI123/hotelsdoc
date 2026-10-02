<?php

declare(strict_types=1);

namespace App\Domain\POS\Actions;

use App\Models\PosOrder;
use App\Support\Money;
use App\Support\Vat;

/**
 * Sums the order's line items into the order's subtotal/tax_total/total_amount,
 * applying the hotel's VAT setting to each line.
 * Called whenever items change.
 */
final class RecalculatePosOrderTotals
{
    public function execute(PosOrder $order): PosOrder
    {
        // load() not loadMissing() — items may already be loaded but stale after a
        // sibling insert/update; we need the canonical DB state to sum totals.
        $order->load('items');

        $vat = Vat::forHotel($order->hotel);
        $subtotal = Money::zero();
        $tax = Money::zero();

        foreach ($order->items as $item) {
            // line_total is the menu price × quantity; VAT is split out of it or added on top.
            $split = $vat->split($item->line_total);

            if (! $item->tax_amount->equals($split['tax'])) {
                $item->update(['tax_amount' => $split['tax']]);
            }

            $subtotal = $subtotal->add($split['net']);
            $tax = $tax->add($split['tax']);
        }

        $order->update([
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total_amount' => $subtotal->add($tax),
        ]);

        return $order->fresh() ?? $order;
    }
}
