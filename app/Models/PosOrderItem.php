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
 * @property Money $tax_amount
 * @property Money $line_total
 * @property array<string, mixed>|null $modifiers
 */
class PosOrderItem extends Model
{
    /** @use HasFactory<PosOrderItemFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'pos_order_id',
        'menu_item_id',
        'name_snapshot',
        'quantity',
        'stock_taken',
        'unit_price',
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
            'tax_amount' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'modifiers' => 'array',
            'quantity' => 'integer',
            'stock_taken' => 'integer',
        ];
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
}
