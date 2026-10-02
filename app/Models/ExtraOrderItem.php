<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $extra_order_id
 * @property int $extra_service_id
 * @property string $name_snapshot
 * @property int $quantity
 * @property int $unit_price
 * @property int $line_total
 */
class ExtraOrderItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'hotel_id',
        'extra_order_id',
        'extra_service_id',
        'name_snapshot',
        'quantity',
        'unit_price',
        'line_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExtraOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ExtraOrder::class, 'extra_order_id');
    }

    /**
     * @return BelongsTo<ExtraService, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(ExtraService::class, 'extra_service_id');
    }
}
