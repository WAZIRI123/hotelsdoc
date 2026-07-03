<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $hotel_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $base_occupancy
 * @property int $max_occupancy
 * @property int $max_adults
 * @property int $max_children
 * @property Money $default_rate
 * @property Money $extra_guest_fee
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'code',
        'name',
        'description',
        'base_occupancy',
        'max_occupancy',
        'max_adults',
        'max_children',
        'default_rate',
        'extra_guest_fee',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_rate' => MoneyCast::class.':TZS',
            'extra_guest_fee' => MoneyCast::class.':TZS',
            'is_active' => 'boolean',
            'base_occupancy' => 'integer',
            'max_occupancy' => 'integer',
            'max_adults' => 'integer',
            'max_children' => 'integer',
        ];
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<RatePlan, $this>
     */
    public function ratePlans(): HasMany
    {
        return $this->hasMany(RatePlan::class);
    }
}
