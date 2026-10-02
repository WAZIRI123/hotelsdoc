<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\RatePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $room_type_id
 * @property int|null $meal_plan_id
 * @property string $name
 * @property Money $price
 * @property Money $extra_adult_price
 * @property Money $extra_child_price
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property bool $is_active
 * @property bool $is_agent_rate Contract (STO) rate, only offered when an agent books
 */
class RatePlan extends Model
{
    /** @use HasFactory<RatePlanFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'meal_plan_id',
        'name',
        'price',
        'extra_adult_price',
        'extra_child_price',
        'valid_from',
        'valid_to',
        'is_active',
        'is_agent_rate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'extra_adult_price' => MoneyCast::class,
            'extra_child_price' => MoneyCast::class,
            'valid_from' => 'date:Y-m-d',
            'valid_to' => 'date:Y-m-d',
            'is_active' => 'boolean',
            'is_agent_rate' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * @return BelongsTo<MealPlan, $this>
     */
    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    /**
     * Whether any booked room is priced on this rate plan.
     */
    public function isInUse(): bool
    {
        return ReservationRoom::query()->where('rate_plan_id', $this->id)->exists();
    }
}
