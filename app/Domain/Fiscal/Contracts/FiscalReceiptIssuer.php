<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Contracts;

use App\Models\FiscalReceipt;
use App\Models\Folio;

/**
 * Issues a fiscal receipt for a settled folio.
 *
 * MVP: InternalReceiptIssuer just stamps a sequential receipt number.
 * Future: TraVfdIssuer submits the sale to TRA's VFD endpoint and stores
 * the verification code + QR payload on the FiscalReceipt row.
 *
 * Every invoice already flows through this contract — switching to the TRA
 * adapter is one config change, not a refactor.
 */
interface FiscalReceiptIssuer
{
    public function code(): string;

    public function issue(Folio $folio): FiscalReceipt;
}
