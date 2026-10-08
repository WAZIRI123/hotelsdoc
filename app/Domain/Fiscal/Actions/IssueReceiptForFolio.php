<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Actions;

use App\Domain\Fiscal\Contracts\FiscalReceiptIssuer;
use App\Models\FiscalReceipt;
use App\Models\Folio;
use RuntimeException;

/**
 * Issues a fiscal receipt for a folio via the configured FiscalReceiptIssuer.
 *
 * Idempotent: if the folio already has an issued receipt, returns the existing one.
 * MVP issuer is InternalReceiptIssuer; swapping in TraVfdIssuer later is one
 * config change — this caller does not change.
 */
final class IssueReceiptForFolio
{
    public function __construct(private readonly FiscalReceiptIssuer $issuer) {}

    public function execute(Folio $folio): FiscalReceipt
    {
        $existing = FiscalReceipt::query()
            ->where('folio_id', $folio->id)
            ->where('status', 'issued')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($folio->status === 'void') {
            throw new RuntimeException('Cannot issue a receipt for a voided guest billing.');
        }

        return $this->issuer->issue($folio);
    }
}
