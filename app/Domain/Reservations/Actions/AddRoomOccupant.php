<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Services\GuestStayConflicts;
use App\Domain\Reservations\Services\OccupancyChanges;
use App\Models\Guest;
use App\Models\ReservationRoom;
use App\Models\RoomOccupant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Adds a person to a reserved room, enforcing the room type's capacity and
 * charging any extra-guest surcharge from tonight onward when in-house.
 */
final class AddRoomOccupant
{
    public function __construct(
        private readonly OccupancyChanges $occupancy,
        private readonly GuestStayConflicts $conflicts,
    ) {}

    public function execute(
        ReservationRoom $reservationRoom,
        string $ageCategory = 'adult',
        ?int $guestId = null,
        ?User $actor = null,
    ): RoomOccupant {
        if (! in_array($ageCategory, RoomOccupant::AGE_CATEGORIES, true)) {
            throw new InvalidArgumentException("Unknown age category [{$ageCategory}].");
        }

        return DB::transaction(function () use ($reservationRoom, $ageCategory, $guestId, $actor): RoomOccupant {
            $reservationRoom = ReservationRoom::query()->whereKey($reservationRoom->id)->lockForUpdate()->firstOrFail();
            $reservationRoom->load('reservation', 'roomType', 'room');

            $reservation = $reservationRoom->reservation;
            $nights = $this->occupancy->nightsToChange($reservation);

            if ($guestId !== null && $reservation->occupants()->whereNull('room_occupants.checked_out_at')->where('room_occupants.guest_id', $guestId)->exists()) {
                throw new RuntimeException('This guest is already staying in a room on this reservation.');
            }

            if ($guestId !== null && ! Guest::query()->whereKey($guestId)->exists()) {
                throw new RuntimeException('Unknown guest.');
            }

            if ($guestId !== null) {
                $this->conflicts->assertNotStayingElsewhere($reservation, $guestId);
            }

            $adults = $reservationRoom->adults + ($ageCategory === 'adult' ? 1 : 0);
            $children = $reservationRoom->children + ($ageCategory === 'child' ? 1 : 0);

            $this->occupancy->assertFits($reservationRoom->roomType, $adults, $children);

            $occupant = $reservationRoom->occupants()->create([
                'guest_id' => $guestId,
                'age_category' => $ageCategory,
                'is_primary' => false,
                'checked_in_at' => $reservation->status === 'checked_in' ? now() : null,
                'added_by' => $actor?->id,
            ]);

            $this->occupancy->applyHeadCount($reservationRoom, $adults, $children, $nights);
            $this->occupancy->ensurePrimary($reservationRoom);

            return $occupant->refresh();
        });
    }
}
