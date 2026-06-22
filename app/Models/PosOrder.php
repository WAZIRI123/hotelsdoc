<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\PosOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $hotel_id
 * @property int $outlet_id
 * @property int|null $folio_id
 * @property string $reference
 * @property string|null $table_no
 * @property string $status
 * @property Money $subtotal
 * @property Money $tax_total
 * @property Money $total_amount
 * @property Carbon|null $billed_at
 */
class PosOrder extends Model
{
    /** @use HasFactory<PosOrderFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUSES = ['open', 'sent', 'billed', 'voided'];

    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'folio_id',
        'opened_by',
        'reference',
        'table_no',
        'status',
        'subtotal',
        'tax_total',
        'total_amount',
        'notes',
        'billed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => MoneyCast::class.':TZS',
            'tax_total' => MoneyCast::class.':TZS',
            'total_amount' => MoneyCast::class.':TZS',
            'billed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PosOrder $order): void {
            $order->uuid ??= (string) Str::uuid();
            $order->reference ??= 'PO-'.strtoupper(Str::random(8));
        });
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    /**
     * @return HasMany<PosOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class);
    }
}
