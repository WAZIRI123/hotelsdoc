<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\FolioChargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $hotel_id
 * @property int $folio_id
 * @property string $source_type
 * @property int|null $source_id
 * @property string $description
 * @property int $quantity
 * @property Money $unit_price
 * @property Money $tax_amount
 * @property Money $total
 * @property int|null $posted_by
 */
class FolioCharge extends Model
{
    /** @use HasFactory<FolioChargeFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'folio_id',
        'source_type',
        'source_id',
        'description',
        'quantity',
        'unit_price',
        'tax_amount',
        'total',
        'posted_at',
        'posted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'total' => MoneyCast::class,
            'posted_at' => 'datetime',
            'quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FolioCharge $charge): void {
            $charge->uuid ??= (string) Str::uuid();
        });
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
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
