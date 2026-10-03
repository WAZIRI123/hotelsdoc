<?php

declare(strict_types=1);

namespace App\Domain\Rates\Services;

use App\Models\RateCalendar;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Support\Money;
use RuntimeException;

/**
 * Resolves per-night rates for a stay by layering:
 *   1. Rate plan base price + extra-adult/child surcharges
 *   2. Rate calendar per-date overrides (price, stop_sell, min_stay)
 *
 * A "night" is a Y-m-d string in the arrival..departure-1 range.
 */
final class RatePricingService
{
    /**
     * @param  list<string>  $nights
     * @return array<string, Money> keyed by Y-m-d date
     */
    public function resolveNightlyRates(
        RatePlan $ratePlan,
        RoomType $roomType,
        int $adults,
        int $children,
        array $nights,
    ): array {
        if ($nights === []) {
            return [];
        }

        $calendar = $this->loadCalendar($ratePlan, $nights);
        $extraGuestSurcharge = $this->extraGuestSurcharge($ratePlan, $roomType, $adults, $children);

        $rates = [];
        foreach ($nights as $night) {
            $baseForNight = $calendar[$night]->price ?? $ratePlan->price;
            $rates[$night] = $baseForNight->add($extraGuestSurcharge);
        }

        return $rates;
    }

    /**
     * The minimum stay is only checked for a stay that starts on the first of
     * the given nights; extending a stay or re-pricing its remaining nights
     * passes $checkMinStay = false.
     *
     * @param  list<string>  $nights
     */
    public function assertBookable(RatePlan $ratePlan, array $nights, bool $checkMinStay = true): void
    {
        if ($nights === []) {
            return;
        }

        $calendar = $this->loadCalendar($ratePlan, $nights);

        foreach ($nights as $night) {
            $row = $calendar[$night] ?? null;
            if ($row?->stop_sell) {
                throw new RuntimeException("Rate plan is closed for {$night}.");
            }
        }

        if (! $checkMinStay) {
            return;
        }

        $arrival = $nights[0];
        $arrivalRow = $calendar[$arrival] ?? null;
        if ($arrivalRow?->min_stay !== null && count($nights) < $arrivalRow->min_stay) {
            throw new RuntimeException("This rate plan requires a minimum stay of {$arrivalRow->min_stay} nights for arrivals on {$arrival}.");
        }
    }

    /**
     * Per-night surcharge a rate plan adds for guests above the room type's base occupancy.
     */
    public function extraGuestSurcharge(RatePlan $plan, RoomType $roomType, int $adults, int $children): Money
    {
        $surcharge = Money::zero();

        $extraAdults = max(0, $adults - $roomType->base_occupancy);
        if ($extraAdults > 0 && $plan->extra_adult_price->isPositive()) {
            $surcharge = $surcharge->add($plan->extra_adult_price->multiply($extraAdults));
        }

        if ($children > 0 && $plan->extra_child_price->isPositive()) {
            $surcharge = $surcharge->add($plan->extra_child_price->multiply($children));
        }

        return $surcharge;
    }

    /**
     * @param  list<string>  $nights
     * @return array<string, RateCalendar> keyed by Y-m-d
     */
    private function loadCalendar(RatePlan $ratePlan, array $nights): array
    {
        return RateCalendar::query()
            ->where('rate_plan_id', $ratePlan->id)
            ->whereIn('date', $nights)
            ->get()
            ->keyBy(fn (RateCalendar $c) => $c->date->toDateString())
            ->all();
    }
}
