<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\Support\HotelCurrency;
use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cast an integer column (minor units) to a Money value object.
 *
 * Usage on a model:
 *   protected $casts = [
 *       'total_amount' => MoneyCast::class,
 *   ];
 *
 * The currency is the row's own `currency` column when it has one, otherwise
 * the currency of the hotel the row belongs to. A fixed currency may still be
 * passed as a cast argument (MoneyCast::class.':USD').
 *
 * @implements CastsAttributes<Money, Money|int>
 */
final class MoneyCast implements CastsAttributes
{
    public function __construct(private readonly ?string $currency = null) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return new Money((int) $value, $this->currencyFor($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (is_int($value)) {
            return [$key => $value];
        }

        $currency = $this->currencyFor($attributes);

        if ($value->currency !== $currency) {
            throw new InvalidArgumentException(
                "Currency mismatch on {$key}: expected {$currency}, got {$value->currency}",
            );
        }

        return [$key => $value->amount];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function currencyFor(array $attributes): string
    {
        if ($this->currency !== null) {
            return $this->currency;
        }

        if (is_string($attributes['currency'] ?? null) && $attributes['currency'] !== '') {
            return $attributes['currency'];
        }

        return HotelCurrency::forHotel(isset($attributes['hotel_id']) ? (int) $attributes['hotel_id'] : null);
    }
}
