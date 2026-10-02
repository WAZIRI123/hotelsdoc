<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Services\OccupancyChanges;
use App\Domain\Reservations\Services\RoomNightRates;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomMove;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Moves everyone in a reserved room to another physical room for the
 * remaining nights, recording the move so the room history is kept.
 *
 * rate_action 'keep_rate' leaves the snapshotted nightly rates untouched
 * (typical when the hotel caused the move); 'new_rate' re-prices the
 * remaining nights at the new room type's rate and bills the difference.
 */
final class MoveRoom
{
    public function __construct(
        private readonly OccupancyChanges $occupancy,
        private readonly RoomNightRates $rates,
    ) {}

    public function execute(
        ReservationRoom $reservationRoom,
        Room $toRoom,
        string $rateAction = 'keep_rate',
        ?string $reason = null,
        ?User $actor = null,
    ): RoomMove {
        if (! in_array($rateAction, RoomMove::RATE_ACTIONS, true)) {
            throw new InvalidArgumentException("Unknown rate action [{$rateAction}].");
        }

        return DB::transaction(function () use ($reservationRoom, $toRoom, $rateAction, $reason, $actor): RoomMove {
            $reservationRoom = ReservationRoom::query()->whereKey($reservationRoom->id)->lockForUpdate()->firstOrFail();
            $reservationRoom->load('reservation', 'room', 'ratePlan', 'nights');
            $toRoom = Room::query()->whereKey($toRoom->id)->lockForUpdate()->firstOrFail();
            $toRoom->load('roomType');

            $reservation = $reservationRoom->reservation;
            $fromRoom = $reservationRoom->room;

            if ($fromRoom?->id === $toRoom->id) {
                throw new RuntimeException("The guests are already in room {$toRoom->number}.");
            }

            $nights = $this->occupancy->nightsToChange($reservation);
            $this->occupancy->assertRoomCanBeTaken($toRoom, $nights);
            $this->occupancy->assertFits($toRoom->roomType, $reservationRoom->adults, $reservationRoom->children);

            RoomAvailability::query()
                ->where('reservation_id', $reservation->id)
                ->when($fromRoom, fn ($query, Room $room) => $query->where('room_id', $room->id))
                ->whereIn('date', $nights)
                ->delete();

            foreach ($nights as $night) {
                RoomAvailability::query()->updateOrCreate(
                    ['room_id' => $toRoom->id, 'date' => $night],
                    ['status' => 'booked', 'reservation_id' => $reservation->id],
                );
            }

            $rateDifference = $rateAction === 'new_rate'
                ? $this->repriceForNewRoom($reservationRoom, $toRoom, $nights)
                : Money::zero();

            $reservationRoom->update([
                'room_id' => $toRoom->id,
                'room_type_id' => $toRoom->room_type_id,
            ]);

            if ($reservation->status === 'checked_in') {
                $fromRoom?->update(['status' => 'cleaning']);
                $toRoom->update(['status' => 'occupied']);
            }

            $this->occupancy->postFolioCharge(
                $reservation,
                $reservationRoom,
                'Room move '.($fromRoom->number ?? '—')." → {$toRoom->number} — rate difference (".count($nights).' night(s))',
                $rateDifference,
                count($nights),
            );

            return RoomMove::query()->create([
                'reservation_room_id' => $reservationRoom->id,
                'from_room_id' => $fromRoom?->id,
                'to_room_id' => $toRoom->id,
                'effective_date' => $nights[0],
                'rate_action' => $rateAction,
                'reason' => $reason,
                'moved_by' => $actor?->id,
            ]);
        });
    }

    /**
     * @param  list<string>  $nights
     * @return Money total change across the re-priced nights
     */
    private function repriceForNewRoom(ReservationRoom $reservationRoom, Room $toRoom, array $nights): Money
    {
        $ratePlan = $reservationRoom->ratePlan?->room_type_id === $toRoom->room_type_id
            ? $reservationRoom->ratePlan
            : null;

        $newRates = $this->rates->forStay(
            $ratePlan, null, $toRoom->roomType, $reservationRoom->adults, $reservationRoom->children, $nights,
        );

        $difference = Money::zero();

        foreach ($reservationRoom->nights as $night) {
            $date = $night->date->toDateString();

            if (! isset($newRates[$date])) {
                continue;
            }

            $difference = $difference->add($newRates[$date]->subtract($night->rate));
            $night->update(['rate' => $newRates[$date]]);
        }

        $reservationRoom->update([
            'rate_plan_id' => $ratePlan?->id,
            'nightly_rate' => $newRates[$nights[0]],
        ]);

        return $difference;
    }
}
