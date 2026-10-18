<?php

declare(strict_types=1);

namespace App\Domain\POS\Actions;

use App\Models\PosOrder;
use App\Support\Money;
use App\Support\Vat;

/**
 * Sums the order's line items into the order's subtotal/tax_total/total_amount,
 * applying the hotel's VAT setting to each line, and splits what each line's
 * discount took off the same way so reports keep the VAT of the sale.
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
            $amountOff = $vat->split($item->amountOff());

            if (! $item->tax_amount->equals($split['tax'])
                || ! $item->amount_off_net->equals($amountOff['net'])
                || ! $item->amount_off_gross->equals($amountOff['gross'])) {
                $item->update([
                    'tax_amount' => $split['tax'],
                    'amount_off_net' => $amountOff['net'],
                    'amount_off_gross' => $amountOff['gross'],
                ]);
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
