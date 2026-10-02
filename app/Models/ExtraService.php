<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\ExtraServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A billable extra (laundry, airport transfer, spa…) with a default price
 * that front-desk staff can sell to in-house guests or walk-in customers.
 *
 * @property int $id
 * @property int $hotel_id
 * @property string $name
 * @property string|null $description
 * @property Money $price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExtraService extends Model
{
    /** @use HasFactory<ExtraServiceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'price',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ExtraOrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(ExtraOrderItem::class);
    }
}
