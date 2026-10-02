<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money;
use Database\Factories\ExtraOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Amounts are integer minor units in the order's own currency, so they are
 * not cast to Money (MoneyCast is fixed to one currency).
 *
 * @property int $id
 * @property string $uuid
 * @property int $hotel_id
 * @property int|null $stay_id
 * @property int|null $folio_id
 * @property int|null $created_by
 * @property string $reference
 * @property string $currency
 * @property Carbon $order_date
 * @property int $total_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExtraOrder extends Model
{
    /** @use HasFactory<ExtraOrderFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'stay_id',
        'folio_id',
        'created_by',
        'reference',
        'currency',
        'order_date',
        'total_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'total_amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExtraOrder $order): void {
            $order->uuid ??= (string) Str::uuid();
            $order->reference ??= 'EX-'.strtoupper(Str::random(8));
        });
    }

    public function total(): Money
    {
        return new Money($this->total_amount, $this->currency);
    }

    public function isWalkIn(): bool
    {
        return $this->stay_id === null;
    }

    /**
     * @return BelongsTo<Stay, $this>
     */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class);
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ExtraOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExtraOrderItem::class);
    }
}
