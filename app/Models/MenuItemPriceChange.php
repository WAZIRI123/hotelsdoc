<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\MenuItemPriceChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One edit of a menu item's price, kept so past prices can be looked up.
 *
 * @property int $id
 * @property int $hotel_id
 * @property int $menu_item_id
 * @property Money $old_price
 * @property Money $new_price
 * @property int|null $changed_by
 */
class MenuItemPriceChange extends Model
{
    /** @use HasFactory<MenuItemPriceChangeFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'menu_item_id',
        'old_price',
        'new_price',
        'changed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_price' => MoneyCast::class,
            'new_price' => MoneyCast::class,
        ];
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
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
