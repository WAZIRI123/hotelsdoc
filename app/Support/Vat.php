<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Tenancy\TenantContext;
use App\Models\Hotel;

/**
 * A hotel's VAT setting, and how it turns an entered price into net, VAT and
 * gross amounts.
 *
 * - none:      no VAT; the price is what the guest pays.
 * - inclusive: entered prices already contain VAT; the guest pays the price
 *              and the VAT part is split out of it.
 * - exclusive: entered prices are before VAT; VAT is added on top.
 */
final readonly class Vat
{
    public const NONE = 'none';

    public const INCLUSIVE = 'inclusive';

    public const EXCLUSIVE = 'exclusive';

    public const MODES = [
        self::NONE => 'No VAT',
        self::INCLUSIVE => 'VAT included in prices',
        self::EXCLUSIVE => 'VAT added on top of prices',
    ];

    public function __construct(
        public string $mode = self::NONE,
        public float $rate = 0.0,
    ) {}

    public static function forHotel(Hotel $hotel): self
    {
        return new self($hotel->vat_mode, (float) $hotel->vat_rate);
    }

    /**
     * The active tenant's VAT setting; no VAT outside a tenant.
     */
    public static function current(): self
    {
        if (! app()->bound(TenantContext::class) || ! app(TenantContext::class)->has()) {
            return new self;
        }

        return self::forHotel(app(TenantContext::class)->current());
    }

    public function applies(): bool
    {
        return $this->mode !== self::NONE && $this->rate > 0;
    }

    /**
     * Split an entered price (positive, or negative for a credit) into the
     * amount before VAT, the VAT and what the guest pays.
     *
     * @return array{net: Money, tax: Money, gross: Money}
     */
    public function split(Money $price): array
    {
        $tax = match (true) {
            ! $this->applies() => 0,
            $this->mode === self::INCLUSIVE => (int) round($price->amount * $this->rate / (100 + $this->rate)),
            default => (int) round($price->amount * $this->rate / 100),
        };

        $tax = new Money($tax, $price->currency);

        return $this->mode === self::EXCLUSIVE
            ? ['net' => $price, 'tax' => $tax, 'gross' => $price->add($tax)]
            : ['net' => $price->subtract($tax), 'tax' => $tax, 'gross' => $price];
    }

    /**
     * Label for the VAT line on bills and receipts, e.g. "VAT 18% (included)".
     */
    public function label(): string
    {
        if (! $this->applies()) {
            return __('VAT');
        }

        $rate = rtrim(rtrim(number_format($this->rate, 2), '0'), '.');

        return $this->mode === self::INCLUSIVE
            ? __('VAT :rate% (included)', ['rate' => $rate])
            : __('VAT :rate%', ['rate' => $rate]);
    }
}
