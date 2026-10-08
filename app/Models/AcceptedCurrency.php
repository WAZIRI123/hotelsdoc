<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\AcceptedCurrencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A foreign currency the hotel takes payment in, besides its own currency.
 *
 * @property int $id
 * @property int $hotel_id
 * @property string $code
 * @property string $rate Hotel currency units for one unit of this currency, decimal string
 * @property bool $is_active
 * @property int|null $updated_by
 */
class AcceptedCurrency extends Model
{
    /** @use HasFactory<AcceptedCurrencyFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'code',
        'rate',
        'is_active',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The rate without trailing zeros, e.g. "2600" or "2.5".
     */
    public function displayRate(): string
    {
        return rtrim(rtrim($this->rate, '0'), '.');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
