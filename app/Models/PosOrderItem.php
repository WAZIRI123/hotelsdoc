<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\PosOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $pos_order_id
 * @property int $menu_item_id
 * @property string $name_snapshot
 * @property int $quantity
 * @property int $stock_taken
 * @property Money $unit_price
 * @property Money|null $original_unit_price
 * @property Money $discount_amount
 * @property Money $amount_off_net
 * @property Money $amount_off_gross
 * @property string|null $adjustment_reason
 * @property int|null $adjusted_by
 * @property Money $tax_amount
 * @property Money $line_total
 * @property array<string, mixed>|null $modifiers
 */
class PosOrderItem extends Model
{
    /** @use HasFactory<PosOrderItemFactory> */
    use BelongsToTenant, HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'discount_amount' => 0,
        'amount_off_net' => 0,
        'amount_off_gross' => 0,
    ];

    protected $fillable = [
        'hotel_id',
        'pos_order_id',
        'menu_item_id',
        'name_snapshot',
        'quantity',
        'stock_taken',
        'unit_price',
        'original_unit_price',
        'discount_amount',
        'amount_off_net',
        'amount_off_gross',
        'adjustment_reason',
        'adjusted_by',
        'tax_amount',
        'line_total',
        'modifiers',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => MoneyCast::class,
            'original_unit_price' => MoneyCast::class,
            'discount_amount' => MoneyCast::class,
            'amount_off_net' => MoneyCast::class,
            'amount_off_gross' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'modifiers' => 'array',
            'quantity' => 'integer',
            'stock_taken' => 'integer',
        ];
    }

    /**
     * Whether a manager changed the price or took a discount off this line.
     */
    public function isAdjusted(): bool
    {
        return $this->original_unit_price !== null || $this->discount_amount->isPositive();
    }

    /**
     * How much the line came to below its rung-up price: the discount plus
     * any price cut by an override (a price raised by an override is not a
     * discount). Same terms as line_total, so VAT splits out of it the same way.
     */
    public function amountOff(): Money
    {
        $priceCut = $this->original_unit_price === null
            ? Money::zero($this->unit_price->currency)
            : $this->original_unit_price->subtract($this->unit_price)->multiply($this->quantity);

        return $this->discount_amount->add($priceCut->isPositive() ? $priceCut : Money::zero($priceCut->currency));
    }

    /**
     * What the line charges for a quantity: unit price × quantity less the
     * discount, the discount never taking it below zero.
     */
    public function totalFor(int $quantity): Money
    {
        $gross = $this->unit_price->multiply($quantity);
        $discount = $this->discount_amount->amount > $gross->amount ? $gross : $this->discount_amount;

        return $gross->subtract($discount);
    }

    /**
     * @return BelongsTo<PosOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
