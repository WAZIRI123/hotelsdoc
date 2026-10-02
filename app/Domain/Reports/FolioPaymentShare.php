<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Models\Folio;
use App\Support\Money;

/**
 * Works out how much of a sale posted to a folio has been paid.
 *
 * Payments are made against the whole folio, which can also hold room and
 * other charges, so a sale is credited with the share of the folio that has
 * been paid. Expects the folio's payments to be eager loaded.
 */
final class FolioPaymentShare
{
    public static function paid(?Folio $folio, Money $amount): Money
    {
        if ($folio === null || ! $folio->total_amount->isPositive()) {
            return Money::zero($amount->currency);
        }

        return $amount->multiply(min(1, $folio->paid_total->amount / $folio->total_amount->amount));
    }

    /**
     * Readable payment methods used on the folio, led by "Room Bill" for in-house guests.
     */
    public static function paidBy(?Folio $folio): string
    {
        if ($folio === null) {
            return '';
        }

        $methods = $folio->payments
            ->where('status', 'confirmed')
            ->pluck('method_code')
            ->unique()
            ->map(fn (string $code): string => ucwords(str_replace('_', ' ', $code)))
            ->values()
            ->all();

        if ($folio->stay_id !== null) {
            array_unshift($methods, __('Room Bill'));
        }

        return implode(', ', $methods);
    }
}
