<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Issuers;

use App\Domain\Fiscal\Contracts\FiscalReceiptIssuer;
use App\Models\FiscalReceipt;
use App\Models\Folio;
use Illuminate\Support\Facades\DB;

/**
 * MVP fiscal issuer: assigns a per-tenant sequential receipt number and stamps
 * the receipt as issued, without contacting any external system.
 *
 * Replace with TraVfdIssuer once TRA VFD integration is in place.
 */
final class InternalReceiptIssuer implements FiscalReceiptIssuer
{
    public function code(): string
    {
        return 'internal';
    }

    public function issue(Folio $folio): FiscalReceipt
    {
        return DB::transaction(function () use ($folio): FiscalReceipt {
            $next = FiscalReceipt::query()
                ->where('hotel_id', $folio->hotel_id)
                ->lockForUpdate()
                ->max('sequence') ?? 0;

            return FiscalReceipt::query()->create([
                'hotel_id' => $folio->hotel_id,
                'folio_id' => $folio->id,
                'provider' => $this->code(),
                'sequence' => $next + 1,
                'receipt_no' => sprintf('R-%06d', $next + 1),
                'status' => 'issued',
                'total_amount' => $folio->total_amount,
                'issued_at' => now(),
            ]);
        });
    }
}
