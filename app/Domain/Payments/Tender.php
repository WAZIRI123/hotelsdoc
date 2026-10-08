<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Support\Money;
use InvalidArgumentException;

/**
 * What a customer handed over for a payment: an amount in the hotel's own
 * currency, or in one of its accepted currencies at the rate set for it.
 */
final readonly class Tender
{
    /**
     * @param  string  $rate  Hotel currency units for one unit of the tendered currency
     */
    private function __construct(
        public Money $amount,
        public string $rate,
        public string $localCurrency,
    ) {
        if ((float) $rate <= 0) {
            throw new InvalidArgumentException('An exchange rate must be positive.');
        }
    }

    public static function local(Money $amount): self
    {
        return new self($amount, '1', $amount->currency);
    }

    public static function foreign(Money $amount, string $rate, string $localCurrency): self
    {
        return new self($amount, $rate, $localCurrency);
    }

    public function isForeign(): bool
    {
        return $this->amount->currency !== $this->localCurrency;
    }

    /**
     * The tendered amount converted to the hotel currency.
     */
    public function localValue(): Money
    {
        return $this->isForeign()
            ? new Money((int) round($this->amount->amount * (float) $this->rate), $this->localCurrency)
            : $this->amount;
    }

    public function covers(Money $due): bool
    {
        return $this->localValue()->amount >= $due->amount;
    }

    /**
     * Change due back, in the hotel currency, after paying the given amount.
     *
     * Foreign money can only be handed over to the cent, so a leftover worth
     * less than one cent of it is rounding, not change.
     */
    public function changeFor(Money $paid): ?Money
    {
        $change = $this->localValue()->subtract($paid);

        if (! $change->isPositive()) {
            return null;
        }

        $oneCentInLocalMinorUnits = $this->isForeign() ? (float) $this->rate : 0.0;

        return $change->amount < $oneCentInLocalMinorUnits ? null : $change;
    }

    /**
     * What a hotel-currency amount comes to in the given currency, rounded up
     * to the cent so it always covers the amount.
     */
    public static function convertFromLocal(Money $local, string $currency, string $rate): Money
    {
        return new Money((int) ceil($local->amount / (float) $rate - 1e-9), $currency);
    }
}
