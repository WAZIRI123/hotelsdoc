<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Tenancy\TenantContext;
use App\Models\Hotel;

/**
 * Resolves the currency money is held in.
 *
 * Each hotel runs in a single currency (hotels.currency), so every amount a
 * hotel stores is in that currency. Lookups are memoised per request and
 * flushed whenever a hotel is saved.
 */
final class HotelCurrency
{
    public const FALLBACK = 'TZS';

    /** @var array<int, string> */
    private static array $byHotelId = [];

    /**
     * The active tenant's currency, or the fallback outside a tenant (console, admin).
     */
    public static function current(): string
    {
        if (! app()->bound(TenantContext::class)) {
            return self::FALLBACK;
        }

        $context = app(TenantContext::class);

        return $context->has() ? $context->current()->currency : self::FALLBACK;
    }

    public static function forHotel(?int $hotelId): string
    {
        if ($hotelId === null) {
            return self::current();
        }

        return self::$byHotelId[$hotelId] ??= Hotel::query()->whereKey($hotelId)->value('currency') ?? self::current();
    }

    public static function flush(): void
    {
        self::$byHotelId = [];
    }
}
