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
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property Carbon|null $cleared_at
 * @property int|null $cleared_by
 */
class PosOrder extends Model
{
    /** @use HasFactory<PosOrderFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUSES = ['open', 'billed', 'voided', 'cleared'];

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
        'voided_at',
        'voided_by',
        'void_reason',
        'cleared_at',
        'cleared_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'total_amount' => MoneyCast::class,
            'billed_at' => 'datetime',
            'voided_at' => 'datetime',
            'cleared_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    /**
     * @return HasMany<PosOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class);
    }

    /**
     * Put the stock taken by this order's items back on hand. Only the units
     * a line actually took come back, so lines rung up before an item was
     * tracked never add stock that was never there.
     */
    public function restoreStock(): void
    {
        $this->loadMissing('items.menuItem');

        foreach ($this->items as $line) {
            if ($line->stock_taken === 0) {
                continue;
            }

            if ($line->menuItem?->track_stock) {
                $line->menuItem->increment('stock_quantity', $line->stock_taken);
            }

            $line->update(['stock_taken' => 0]);
        }
    }
}
