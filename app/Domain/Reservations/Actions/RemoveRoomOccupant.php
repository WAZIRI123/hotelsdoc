<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Services\OccupancyChanges;
use App\Models\RoomOccupant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Takes a person out of a room. Before arrival the placeholder is simply
 * deleted; once in-house the row is ended (never deleted) so the guest
 * register keeps the history, and the extra-guest surcharge stops from tonight.
 */
final class RemoveRoomOccupant
{
    public function __construct(private readonly OccupancyChanges $occupancy) {}

    public function execute(RoomOccupant $occupant, ?User $actor = null): void
    {
        DB::transaction(function () use ($occupant, $actor): void {
            $occupant->refresh()->load('reservationRoom.reservation', 'reservationRoom.room');

            if (! $occupant->isActive()) {
                throw new RuntimeException('This person has already left the room.');
            }

            $reservationRoom = $occupant->reservationRoom;
            $nights = $this->occupancy->nightsToChange($reservationRoom->reservation);

            $this->assertRoomKeepsAnAdult($occupant);

            if ($occupant->checked_in_at === null) {
                $occupant->delete();
            } else {
                $occupant->update([
                    'checked_out_at' => now(),
                    'removed_by' => $actor?->id,
                    'is_primary' => false,
                ]);
            }

            $this->occupancy->applyHeadCount(
                $reservationRoom,
                $reservationRoom->adults - ($occupant->isChild() ? 0 : 1),
                $reservationRoom->children - ($occupant->isChild() ? 1 : 0),
                $nights,
            );

            $this->occupancy->ensurePrimary($reservationRoom);
        });
    }

    private function assertRoomKeepsAnAdult(RoomOccupant $occupant): void
    {
        $remaining = $occupant->reservationRoom->activeOccupants()->whereKeyNot($occupant->id)->get();
        $roomNumber = $occupant->reservationRoom->room->number ?? '—';

        if ($remaining->isEmpty()) {
            throw new RuntimeException("Room {$roomNumber} must keep at least one guest. Check out or cancel the room instead.");
        }

        if ($remaining->every(fn (RoomOccupant $other): bool => $other->isChild())) {
            throw new RuntimeException("Room {$roomNumber} cannot be left with only children.");
        }
    }
}
