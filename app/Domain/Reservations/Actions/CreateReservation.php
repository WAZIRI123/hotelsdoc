<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Domain\Reservations\Services\OccupancyChanges;
use App\Domain\Reservations\Services\RoomNightRates;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CreateReservation
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly RoomNightRates $rates,
        private readonly OccupancyChanges $occupancy,
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
        ?int $agentId = null,
    ): Reservation {
        if (CarbonImmutable::instance($arrival)->startOfDay()->isBefore(CarbonImmutable::today())) {
            throw new RuntimeException('The arrival date cannot be in the past.');
        }

        return DB::transaction(function () use (
            $guestId, $roomTypeId, $roomId, $arrival, $departure,
            $adults, $children, $ratePlanId, $nightlyRate, $source, $externalId, $notes, $agentId,
        ): Reservation {
            $room = Room::query()->whereKey($roomId)->lockForUpdate()->firstOrFail();

            if ($room->room_type_id !== $roomTypeId) {
                throw new RuntimeException('Selected room does not belong to the chosen room type.');
            }

            if ($adults < 1) {
                throw new RuntimeException('A room needs at least one adult.');
            }

            $roomType = RoomType::query()->findOrFail($roomTypeId);

            $this->occupancy->assertFits($roomType, $adults, $children);

            if ($room->isOutOfService()) {
                throw new RuntimeException("Room {$room->number} is out of service.");
            }

            if (! $this->availability->isRoomAvailable($room->id, $arrival, $departure)) {
                throw new RuntimeException("Room {$room->number} is no longer available for the requested dates.");
            }

            $nights = $this->availability->nights($arrival, $departure);
            $ratePlan = $ratePlanId !== null ? RatePlan::query()->findOrFail($ratePlanId) : null;
            $perNightRates = $this->rates->forStay($ratePlan, $nightlyRate, $roomType, $adults, $children, $nights);

            $displayRate = $perNightRates[$nights[0]];

            $reservation = Reservation::query()->create([
                'guest_id' => $guestId,
                'agent_id' => $agentId,
                'arrival' => $arrival,
                'departure' => $departure,
                'adults' => $adults,
                'children' => $children,
                'status' => 'confirmed',
                'source' => $agentId !== null && $source === 'walk_in' ? 'agent' : $source,
                'external_id' => $externalId,
                'notes' => $notes,
            ]);

            /** @var ReservationRoom $reservationRoom */
            $reservationRoom = $reservation->rooms()->create([
                'room_type_id' => $roomTypeId,
                'room_id' => $room->id,
                'rate_plan_id' => $ratePlanId,
                'adults' => $adults,
                'children' => $children,
                'nightly_rate' => $displayRate,
            ]);

            foreach ($perNightRates as $night => $rate) {
                $reservationRoom->nights()->create([
                    'date' => $night,
                    'rate' => $rate,
                ]);
            }

            $this->createOccupants($reservationRoom, $guestId, $adults, $children);

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
     * The booker becomes the room's primary occupant; everyone else starts as
     * an unnamed placeholder to be identified at check-in.
     */
    private function createOccupants(ReservationRoom $reservationRoom, int $guestId, int $adults, int $children): void
    {
        $reservationRoom->occupants()->create([
            'guest_id' => $guestId,
            'age_category' => 'adult',
            'is_primary' => true,
        ]);

        for ($i = 1; $i < $adults; $i++) {
            $reservationRoom->occupants()->create(['age_category' => 'adult']);
        }

        for ($i = 0; $i < $children; $i++) {
            $reservationRoom->occupants()->create(['age_category' => 'child']);
        }
    }
}
