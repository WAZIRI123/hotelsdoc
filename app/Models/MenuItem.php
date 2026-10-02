<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $outlet_id
 * @property int $menu_category_id
 * @property int|null $stock_type_id
 * @property string $name
 * @property string|null $description
 * @property Money $price
 * @property string|null $tax_code
 * @property bool $is_active
 * @property bool $track_stock
 * @property int $stock_quantity
 */
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'menu_category_id',
        'stock_type_id',
        'name',
        'description',
        'price',
        'tax_code',
        'is_active',
        'track_stock',
        'stock_quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class.':TZS',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
            'stock_quantity' => 'integer',
        ];
    }

    public function isOutOfStock(): bool
    {
        return $this->track_stock && $this->stock_quantity <= 0;
    }

    public function canFulfillQuantity(int $quantity): bool
    {
        if (! $this->track_stock) {
            return true;
        }

        return $this->stock_quantity >= $quantity;
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /**
     * @return BelongsTo<StockType, $this>
     */
    public function stockType(): BelongsTo
    {
        return $this->belongsTo(StockType::class);
    }
}
