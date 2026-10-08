<?php

declare(strict_types=1);

namespace App\Domain\Folios\Actions;

use App\Models\Folio;
use App\Models\FolioCharge;
use App\Support\Money;
use App\Support\Vat;
use RuntimeException;

/**
 * Takes an agreed amount off what the guest pays, as a 'discount' credit
 * line that the cash-up and profit & loss reports show as discounts. The
 * amount is what comes off the bill, so its VAT share is split out of it
 * whichever VAT mode the hotel uses.
 */
final class ApplyFolioDiscount
{
    public function __construct(private readonly PostFolioCharge $postCharge) {}

    public function execute(Folio $folio, Money $amount, string $reason): FolioCharge
    {
        if (! $amount->isPositive()) {
            throw new RuntimeException('Discount amount must be positive.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('Give a reason for the discount.');
        }

        if ($amount->amount > $folio->total_amount->amount) {
            throw new RuntimeException("The discount cannot be more than the guest billing total of {$folio->total_amount->format()}.");
        }

        $vat = Vat::forHotel($folio->hotel);
        $split = (new Vat($vat->applies() ? Vat::INCLUSIVE : Vat::NONE, $vat->rate))
            ->split(Money::zero($amount->currency)->subtract($amount));

        return $this->postCharge->amounts(
            folio: $folio,
            sourceType: 'discount',
            sourceId: null,
            description: 'Discount — '.trim($reason),
            net: $split['net'],
            tax: $split['tax'],
        );
    }
}
