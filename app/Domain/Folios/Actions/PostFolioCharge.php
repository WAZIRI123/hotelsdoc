<?php

declare(strict_types=1);

namespace App\Domain\Folios\Actions;

use App\Domain\Fiscal\Actions\IssueReceiptForFolio;
use App\Models\FiscalReceipt;
use App\Models\Folio;
use App\Models\FolioCharge;
use App\Support\Money;
use App\Support\Vat;
use RuntimeException;

/**
 * Posts a charge (or a credit, when negative) on a folio and keeps the
 * folio's running totals in step: total = subtotal + tax.
 *
 * Every place that bills a folio — room nights, room adjustments, POS orders
 * and extras — goes through here so VAT is applied the same way everywhere.
 *
 * A charge on a settled folio changes what the guest owes, so its fiscal
 * receipt is superseded: the folio reopens and gets a new receipt once paid
 * again, or is re-receipted at once when a credit leaves it fully paid.
 */
final class PostFolioCharge
{
    public function __construct(private readonly IssueReceiptForFolio $issueReceipt) {}

    /**
     * Post an entered price, splitting VAT out of it (or adding it on top)
     * according to the hotel's VAT setting.
     */
    public function price(
        Folio $folio,
        string $sourceType,
        ?int $sourceId,
        string $description,
        Money $price,
        int $quantity = 1,
    ): FolioCharge {
        $split = Vat::forHotel($folio->hotel)->split($price);

        return $this->amounts($folio, $sourceType, $sourceId, $description, $split['net'], $split['tax'], $quantity);
    }

    /**
     * Post amounts whose VAT is already known, such as a POS order.
     */
    public function amounts(
        Folio $folio,
        string $sourceType,
        ?int $sourceId,
        string $description,
        Money $net,
        Money $tax,
        int $quantity = 1,
    ): FolioCharge {
        if (! $folio->acceptsCharges()) {
            throw new RuntimeException("Cannot post a charge onto a folio in status [{$folio->status}].");
        }

        $gross = $net->add($tax);
        $quantity = max(1, $quantity);

        $charge = $folio->charges()->create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => new Money(intdiv(abs($gross->amount), $quantity), $gross->currency),
            'tax_amount' => $tax,
            'total' => $gross,
            'posted_at' => now(),
        ]);

        $folio->refresh();
        $subtotal = $folio->subtotal->add($net);
        $taxTotal = $folio->tax_total->add($tax);

        $total = $subtotal->add($taxTotal);
        $wasSettled = $folio->status === 'settled';
        $reopen = $wasSettled && $folio->paid_total->amount < $total->amount;

        $folio->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total_amount' => $total,
            'status' => $reopen ? ($folio->closed_at === null ? 'open' : 'closed') : $folio->status,
            'settled_at' => $reopen ? null : $folio->settled_at,
        ]);

        if ($wasSettled) {
            FiscalReceipt::query()
                ->where('folio_id', $folio->id)
                ->where('status', 'issued')
                ->update(['status' => 'superseded']);

            if (! $reopen) {
                $this->issueReceipt->execute($folio);
            }
        }

        return $charge;
    }
}
