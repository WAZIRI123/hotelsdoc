<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable money value object.
 *
 * Stores amounts as integer minor units (cents) to avoid floating-point error.
 * When no currency is given, the active hotel's currency is used (see
 * HotelCurrency); amounts keep a 2-decimal representation internally for
 * arithmetic safety even for currencies like TSh with no subunit in practice.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public string $currency;

    public function __construct(
        public int $amount,
        ?string $currency = null,
    ) {
        $this->currency = $currency ?? HotelCurrency::current();
    }

    public static function fromMinor(int $amount, ?string $currency = null): self
    {
        return new self($amount, $currency);
    }

    public static function fromMajor(int|float $amount, ?string $currency = null): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public static function zero(?string $currency = null): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int|float $factor): self
    {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function toMajor(): float
    {
        return $this->amount / 100;
    }

    public function format(): string
    {
        return $this->currency.' '.number_format($this->toMajor(), 0);
    }

    /**
     * Like format(), but keeps the cents when there are any (USD 12.50).
     */
    public function formatExact(): string
    {
        return $this->currency.' '.number_format($this->toMajor(), $this->amount % 100 === 0 ? 0 : 2);
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * @return array{amount: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} vs {$other->currency}",
            );
        }
    }
}
