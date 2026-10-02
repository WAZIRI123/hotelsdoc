<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\ReservationRoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $reservation_id
 * @property int $room_type_id
 * @property int|null $room_id
 * @property int|null $rate_plan_id
 * @property int $adults
 * @property int $children
 * @property Money $nightly_rate
 */
class ReservationRoom extends Model
{
    /** @use HasFactory<ReservationRoomFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'room_type_id',
        'room_id',
        'rate_plan_id',
        'adults',
        'children',
        'nightly_rate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nightly_rate' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * @return BelongsTo<RatePlan, $this>
     */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    /**
     * Per-night rate snapshots taken at booking time.
     *
     * @return HasMany<ReservationRoomNight, $this>
     */
    public function nights(): HasMany
    {
        return $this->hasMany(ReservationRoomNight::class);
    }

    /**
     * Everyone who has been assigned to this room, including people who have left.
     *
     * @return HasMany<RoomOccupant, $this>
     */
    public function occupants(): HasMany
    {
        return $this->hasMany(RoomOccupant::class);
    }

    /**
     * @return HasMany<RoomOccupant, $this>
     */
    public function activeOccupants(): HasMany
    {
        return $this->hasMany(RoomOccupant::class)->whereNull('checked_out_at');
    }

    /**
     * @return HasMany<RoomMove, $this>
     */
    public function moves(): HasMany
    {
        return $this->hasMany(RoomMove::class);
    }

    public function guestCount(): int
    {
        return $this->adults + $this->children;
    }

    public function totalRate(): Money
    {
        return $this->nights
            ->reduce(fn (Money $carry, ReservationRoomNight $night) => $carry->add($night->rate), Money::zero());
    }
}
