<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Folios\BillSection;
use App\Domain\Folios\FolioSections;
use App\Models\Folio;
use App\Support\Money;

/**
 * Works out how much of a sale posted to a folio has been paid.
 *
 * A folio can hold room, outlet and extras charges. When the sale's section
 * is given, it is credited with the share of that section that has been
 * paid (see FolioSections); otherwise with the share of the whole folio.
 * Expects the folio's payments (and, with a section, charges) to be eager loaded.
 */
final class FolioPaymentShare
{
    public static function paid(?Folio $folio, Money $amount, ?BillSection $section = null): Money
    {
        if ($folio === null || ! $folio->total_amount->isPositive()) {
            return Money::zero($amount->currency);
        }

        if ($section !== null) {
            $row = FolioSections::for($folio)->get($section);

            // A folio with no charges in the section cannot be split, so it falls back to the whole-folio share.
            if ($row['total']->isPositive()) {
                return $amount->multiply(min(1, $row['paid']->amount / $row['total']->amount));
            }
        }

        return $amount->multiply(min(1, $folio->paid_total->amount / $folio->total_amount->amount));
    }

    /**
     * Readable payment methods used on the folio, led by "Room Bill" for in-house guests.
     */
    public static function paidBy(?Folio $folio, ?BillSection $section = null): string
    {
        if ($folio === null) {
            return '';
        }

        $methods = $folio->payments
            ->where('status', 'confirmed')
            ->filter(fn ($payment): bool => $section === null || $payment->bill_section === null || $payment->bill_section === $section->value)
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
