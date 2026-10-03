<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Services\OccupancyChanges;
use App\Domain\Reservations\Services\RoomNightRates;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomOccupant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Moves one person out of their room, either into another room already on
 * the same reservation or into a newly added room. To move everyone in a
 * room, use MoveRoom instead.
 *
 * In-house, the person's old row is ended and a new one started so the
 * register shows where they slept each night; both rooms are re-priced
 * from tonight and the difference is posted to the folio.
 */
final class TransferOccupant
{
    public function __construct(
        private readonly OccupancyChanges $occupancy,
        private readonly RoomNightRates $rates,
    ) {}

    public function execute(
        RoomOccupant $occupant,
        ?ReservationRoom $toReservationRoom = null,
        ?Room $toNewRoom = null,
        ?User $actor = null,
    ): RoomOccupant {
        if (($toReservationRoom === null) === ($toNewRoom === null)) {
            throw new InvalidArgumentException('Transfer to either an existing reserved room or a new room, not both.');
        }

        return DB::transaction(function () use ($occupant, $toReservationRoom, $toNewRoom, $actor): RoomOccupant {
            $occupant->refresh()->load('reservationRoom.reservation', 'reservationRoom.room');

            if (! $occupant->isActive()) {
                throw new RuntimeException('This person has already left the room.');
            }

            $source = $occupant->reservationRoom;
            $reservation = $source->reservation;
            $nights = $this->occupancy->nightsToChange($reservation);

            $this->assertSourceKeepsAnAdult($occupant);

            $isChild = $occupant->isChild();
            $target = $toReservationRoom !== null
                ? $this->prepareExistingTarget($toReservationRoom, $source, $isChild)
                : $this->addRoomToReservation($reservation, $toNewRoom, $isChild, $nights);

            $moved = $this->relocate($occupant, $target, $reservation, $actor);

            $this->occupancy->applyHeadCount(
                $source,
                $source->adults - ($isChild ? 0 : 1),
                $source->children - ($isChild ? 1 : 0),
                $nights,
            );
            $this->occupancy->ensurePrimary($source);

            if ($toReservationRoom !== null) {
                $this->occupancy->applyHeadCount(
                    $target,
                    $target->adults + ($isChild ? 0 : 1),
                    $target->children + ($isChild ? 1 : 0),
                    $nights,
                );
            }

            $this->occupancy->ensurePrimary($target);

            return $moved->refresh();
        });
    }

    private function prepareExistingTarget(ReservationRoom $target, ReservationRoom $source, bool $isChild): ReservationRoom
    {
        $target = ReservationRoom::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
        $target->load('reservation', 'roomType', 'room');

        if ($target->reservation_id !== $source->reservation_id) {
            throw new RuntimeException('Guests can only be transferred between rooms on the same reservation.');
        }

        if ($target->id === $source->id) {
            throw new RuntimeException('This person is already in that room.');
        }

        $this->occupancy->assertFits(
            $target->roomType,
            $target->adults + ($isChild ? 0 : 1),
            $target->children + ($isChild ? 1 : 0),
        );

        return $target;
    }

    /**
     * @param  list<string>  $nights
     */
    private function addRoomToReservation(Reservation $reservation, Room $room, bool $isChild, array $nights): ReservationRoom
    {
        if ($isChild) {
            throw new RuntimeException('A child cannot be moved into a room alone.');
        }

        $room = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
        $room->load('roomType');

        $this->occupancy->assertRoomCanBeTaken($room, $nights, $reservation);
        $this->occupancy->assertFits($room->roomType, 1, 0);

        $rates = $this->rates->forStay(null, null, $room->roomType, 1, 0, $nights);

        /** @var ReservationRoom $reservationRoom */
        $reservationRoom = $reservation->rooms()->create([
            'room_type_id' => $room->room_type_id,
            'room_id' => $room->id,
            'adults' => 1,
            'children' => 0,
            'nightly_rate' => $rates[$nights[0]],
        ]);

        $total = Money::zero();
        foreach ($rates as $night => $rate) {
            $reservationRoom->nights()->create(['date' => $night, 'rate' => $rate]);
            $total = $total->add($rate);
        }

        foreach ($nights as $night) {
            RoomAvailability::query()->updateOrCreate(
                ['room_id' => $room->id, 'date' => $night],
                ['status' => 'booked', 'reservation_id' => $reservation->id],
            );
        }

        if ($reservation->status === 'checked_in') {
            $room->update(['status' => 'occupied']);
        }

        $this->occupancy->postFolioCharge(
            $reservation,
            $reservationRoom,
            "Room {$room->number} — ".count($nights).' night(s)',
            $total,
            count($nights),
        );

        $reservation->syncGuestTotals();

        return $reservationRoom->load('reservation', 'roomType', 'room');
    }

    private function relocate(RoomOccupant $occupant, ReservationRoom $target, Reservation $reservation, ?User $actor): RoomOccupant
    {
        if ($occupant->checked_in_at === null) {
            $occupant->update([
                'reservation_room_id' => $target->id,
                'is_primary' => false,
            ]);

            return $occupant;
        }

        $occupant->update([
            'checked_out_at' => now(),
            'removed_by' => $actor?->id,
            'is_primary' => false,
        ]);

        return $target->occupants()->create([
            'guest_id' => $occupant->guest_id,
            'age_category' => $occupant->age_category,
            'is_primary' => false,
            'checked_in_at' => $reservation->status === 'checked_in' ? now() : null,
            'added_by' => $actor?->id,
        ]);
    }

    private function assertSourceKeepsAnAdult(RoomOccupant $occupant): void
    {
        $remaining = $occupant->reservationRoom->activeOccupants()->whereKeyNot($occupant->id)->get();
        $roomNumber = $occupant->reservationRoom->room->number ?? '—';

        if ($remaining->isEmpty()) {
            throw new RuntimeException("This is the last guest in room {$roomNumber}. Use Move room to move the whole room instead.");
        }

        if (! $occupant->isChild() && $remaining->every(fn (RoomOccupant $other): bool => $other->isChild())) {
            throw new RuntimeException("Room {$roomNumber} cannot be left with only children.");
        }
    }
}
