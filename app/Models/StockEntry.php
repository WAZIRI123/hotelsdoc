<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\StockEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A delivery of stock received for a stock-tracked menu item.
 *
 * @property int $id
 * @property int $hotel_id
 * @property int $menu_item_id
 * @property int|null $recorded_by
 * @property Carbon $entry_date
 * @property Carbon|null $expiry_date
 * @property string|null $description
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StockEntry extends Model
{
    /** @use HasFactory<StockEntryFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'menu_item_id',
        'recorded_by',
        'entry_date',
        'expiry_date',
        'description',
        'quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'expiry_date' => 'date',
            'quantity' => 'integer',
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
