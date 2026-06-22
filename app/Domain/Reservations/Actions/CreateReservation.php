<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CreateReservation
{
    public function __construct(private readonly AvailabilityChecker $availability) {}

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

            if (! $this->availability->isRoomAvailable($room->id, $arrival, $departure)) {
                throw new RuntimeException("Room {$room->number} is no longer available for the requested dates.");
            }

            $rate = $nightlyRate;
            if ($rate === null) {
                if ($ratePlanId !== null) {
                    $rate = RatePlan::query()->findOrFail($ratePlanId)->price;
                } else {
                    $rate = RoomType::query()->findOrFail($roomTypeId)->default_rate;
                }
            }

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

            $reservation->rooms()->create([
                'room_type_id' => $roomTypeId,
                'room_id' => $room->id,
                'rate_plan_id' => $ratePlanId,
                'nightly_rate' => $rate,
            ]);

            foreach ($this->availability->nights($arrival, $departure) as $night) {
                RoomAvailability::query()->updateOrCreate(
                    ['room_id' => $room->id, 'date' => $night],
                    ['status' => 'booked', 'reservation_id' => $reservation->id],
                );
            }

            return $reservation;
        });
    }
}
