<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Services;

use App\Domain\Rates\Services\RatePricingService;
use App\Models\RatePlan;
use App\Models\ReservationRoom;
use App\Models\RoomType;
use App\Support\Money;

/**
 * Prices the nights of a reserved room and re-prices them when its head count changes.
 */
final class RoomNightRates
{
    public function __construct(private readonly RatePricingService $pricing) {}

    /**
     * @param  list<string>  $nights
     * @param  bool  $isNewStay  false when the nights extend or re-price part of an existing stay, so the minimum stay does not apply
     * @return array<string, Money> keyed by Y-m-d
     */
    public function forStay(
        ?RatePlan $ratePlan,
        ?Money $nightlyRateOverride,
        RoomType $roomType,
        int $adults,
        int $children,
        array $nights,
        bool $isNewStay = true,
    ): array {
        if ($nightlyRateOverride !== null) {
            return array_fill_keys($nights, $nightlyRateOverride);
        }

        if ($ratePlan !== null) {
            $this->pricing->assertBookable($ratePlan, $nights, $isNewStay);

            return $this->pricing->resolveNightlyRates($ratePlan, $roomType, $adults, $children, $nights);
        }

        $rate = $roomType->default_rate->add($this->occupancySurcharge(null, $roomType, $adults, $children));

        return array_fill_keys($nights, $rate);
    }

    /**
     * Per-night amount charged for guests above the room type's base occupancy.
     */
    public function occupancySurcharge(?RatePlan $ratePlan, RoomType $roomType, int $adults, int $children): Money
    {
        if ($ratePlan !== null) {
            return $this->pricing->extraGuestSurcharge($ratePlan, $roomType, $adults, $children);
        }

        $extraGuests = max(0, ($adults + $children) - $roomType->base_occupancy);

        if ($extraGuests === 0 || ! $roomType->extra_guest_fee->isPositive()) {
            return Money::zero();
        }

        return $roomType->extra_guest_fee->multiply($extraGuests);
    }

    /**
     * Shift the snapshotted rate of the given nights by the difference in occupancy
     * surcharge between the old and the room's current head count. Adjusting by the
     * difference (rather than re-pricing from scratch) keeps negotiated or
     * overridden base rates intact.
     *
     * @param  list<string>  $nights
     * @return Money total change across the nights (negative when guests left)
     */
    public function applyHeadCountChange(ReservationRoom $reservationRoom, int $oldAdults, int $oldChildren, array $nights): Money
    {
        $reservationRoom->loadMissing('roomType', 'ratePlan', 'nights');

        $ratePlan = $reservationRoom->ratePlan?->room_type_id === $reservationRoom->room_type_id
            ? $reservationRoom->ratePlan
            : null;

        $perNightDelta = $this->occupancySurcharge($ratePlan, $reservationRoom->roomType, $reservationRoom->adults, $reservationRoom->children)
            ->subtract($this->occupancySurcharge($ratePlan, $reservationRoom->roomType, $oldAdults, $oldChildren));

        if ($perNightDelta->isZero()) {
            return Money::zero();
        }

        $total = Money::zero();

        foreach ($reservationRoom->nights as $night) {
            if (! in_array($night->date->toDateString(), $nights, true)) {
                continue;
            }

            $newRate = $night->rate->add($perNightDelta);
            if ($newRate->isNegative()) {
                $newRate = Money::zero();
            }

            $total = $total->add($newRate->subtract($night->rate));
            $night->update(['rate' => $newRate]);
        }

        return $total;
    }
}
