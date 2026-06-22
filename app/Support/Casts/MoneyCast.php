<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cast an integer column (minor units) to a Money value object.
 *
 * Usage on a model:
 *   protected $casts = [
 *       'total_amount' => MoneyCast::class.':TZS',
 *   ];
 *
 * @implements CastsAttributes<Money, Money|int>
 */
final class MoneyCast implements CastsAttributes
{
    public function __construct(private readonly string $currency = 'TZS') {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return new Money((int) $value, $this->currency);
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

        if ($value->currency !== $this->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch on {$key}: expected {$this->currency}, got {$value->currency}",
            );
        }

        return [$key => $value->amount];
    }
}
