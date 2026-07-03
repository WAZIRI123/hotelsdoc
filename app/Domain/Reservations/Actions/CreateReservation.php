<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Rates\Services\RatePricingService;
use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CreateReservation
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly RatePricingService $pricing,
    ) {}

    public function execute(
        int $guestId,
        int $roomTypeId,
        int $roomId,
        CarbonInterface $arrival,
        CarbonInterface $departure,
        int $adults = 1,
        int $children = 0,
        ?int $ratePlanId = null,
        ?Money $nightlyRate = null,
        string $source = 'walk_in',
        ?string $externalId = null,
        ?string $notes = null,
    ): Reservation {
        return DB::transaction(function () use (
            $guestId, $roomTypeId, $roomId, $arrival, $departure,
            $adults, $children, $ratePlanId, $nightlyRate, $source, $externalId, $notes,
        ): Reservation {
            $room = Room::query()->whereKey($roomId)->lockForUpdate()->firstOrFail();

            if ($room->room_type_id !== $roomTypeId) {
                throw new RuntimeException('Selected room does not belong to the chosen room type.');
            }

            $roomType = RoomType::query()->findOrFail($roomTypeId);

            if ($adults > $roomType->max_adults) {
                throw new RuntimeException("This room type allows at most {$roomType->max_adults} adults.");
            }

            if ($children > $roomType->max_children) {
                throw new RuntimeException("This room type allows at most {$roomType->max_children} children.");
            }

            if (($adults + $children) > $roomType->max_occupancy) {
                throw new RuntimeException("This room type sleeps at most {$roomType->max_occupancy} guests total.");
            }

            if (! $this->availability->isRoomAvailable($room->id, $arrival, $departure)) {
                throw new RuntimeException("Room {$room->number} is no longer available for the requested dates.");
            }

            $nights = $this->availability->nights($arrival, $departure);
            $perNightRates = $this->computePerNightRates(
                $ratePlanId, $nightlyRate, $roomType, $adults, $children, $nights,
            );

            $displayRate = $perNightRates[$nights[0]];

            $reservation = Reservation::query()->create([
                'guest_id' => $guestId,
                'arrival' => $arrival,
                'departure' => $departure,
                'adults' => $adults,
                'children' => $children,
                'status' => 'confirmed',
                'source' => $source,
                'external_id' => $externalId,
                'notes' => $notes,
            ]);

            /** @var ReservationRoom $reservationRoom */
            $reservationRoom = $reservation->rooms()->create([
                'room_type_id' => $roomTypeId,
                'room_id' => $room->id,
                'rate_plan_id' => $ratePlanId,
                'nightly_rate' => $displayRate,
            ]);

            foreach ($perNightRates as $night => $rate) {
                $reservationRoom->nights()->create([
                    'date' => $night,
                    'rate' => $rate,
                ]);
            }

            foreach ($nights as $night) {
                RoomAvailability::query()->updateOrCreate(
                    ['room_id' => $room->id, 'date' => $night],
                    ['status' => 'booked', 'reservation_id' => $reservation->id],
                );
            }

            return $reservation;
        });
    }

    /**
     * @param  list<string>  $nights
     * @return array<string, Money> keyed by Y-m-d
     */
    private function computePerNightRates(
        ?int $ratePlanId,
        ?Money $nightlyRateOverride,
        RoomType $roomType,
        int $adults,
        int $children,
        array $nights,
    ): array {
        if ($nightlyRateOverride !== null) {
            return array_fill_keys($nights, $nightlyRateOverride);
        }

        if ($ratePlanId !== null) {
            $ratePlan = RatePlan::query()->findOrFail($ratePlanId);
            $this->pricing->assertBookable($ratePlan, $nights);

            return $this->pricing->resolveNightlyRates($ratePlan, $roomType, $adults, $children, $nights);
        }

        $rate = $roomType->default_rate;
        $extraGuests = max(0, ($adults + $children) - $roomType->base_occupancy);
        if ($extraGuests > 0 && $roomType->extra_guest_fee->isPositive()) {
            $rate = $rate->add($roomType->extra_guest_fee->multiply($extraGuests));
        }

        return array_fill_keys($nights, $rate);
    }
}
