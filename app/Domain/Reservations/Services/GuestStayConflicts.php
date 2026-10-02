<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Services;

use App\Models\Reservation;
use App\Models\RoomOccupant;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Stops one person from sleeping in two places at once: a guest may not be
 * an active occupant on another live reservation whose dates overlap.
 */
final class GuestStayConflicts
{
    /** Reservation statuses in which someone is expected to be in the room. */
    private const LIVE_STATUSES = ['hold', 'confirmed', 'checked_in'];

    /**
     * Each guest's clashing occupancy on another reservation, keyed by guest id.
     *
     * @param  array<int, int>  $guestIds
     * @return Collection<int, RoomOccupant>
     */
    public function forGuests(Reservation $reservation, array $guestIds): Collection
    {
        return $this->forGuestsBetween($guestIds, $reservation->arrival, $reservation->departure, $reservation->id);
    }

    /**
     * Each guest's occupancy on a live reservation overlapping the given dates,
     * keyed by guest id. Used before a reservation exists, e.g. on the booking form.
     *
     * @param  array<int, int>  $guestIds
     * @return Collection<int, RoomOccupant>
     */
    public function forGuestsBetween(array $guestIds, CarbonInterface $arrival, CarbonInterface $departure, ?int $exceptReservationId = null): Collection
    {
        if ($guestIds === []) {
            return new Collection;
        }

        return RoomOccupant::query()
            ->whereIn('guest_id', $guestIds)
            ->whereNull('checked_out_at')
            ->whereHas('reservationRoom.reservation', fn ($query) => $query
                ->when($exceptReservationId !== null, fn ($q) => $q->whereKeyNot($exceptReservationId))
                ->whereIn('status', self::LIVE_STATUSES)
                ->where('arrival', '<', $departure)
                ->where('departure', '>', $arrival))
            ->with('guest', 'reservationRoom.room', 'reservationRoom.reservation')
            ->orderBy('id')
            ->get()
            ->unique('guest_id')
            ->keyBy('guest_id')
            ->toBase();
    }

    public function assertNotStayingElsewhere(Reservation $reservation, int $guestId): void
    {
        $clash = $this->forGuests($reservation, [$guestId])->get($guestId);

        if ($clash === null) {
            return;
        }

        throw new RuntimeException(sprintf(
            '%s is already staying in %s for these dates.',
            $clash->guest?->fullName() ?? 'This guest',
            $this->describe($clash),
        ));
    }

    /**
     * Short label for where the clashing occupancy is, e.g. "Room 205 (RES-123)".
     */
    public function describe(RoomOccupant $clash): string
    {
        $room = $clash->reservationRoom->room->number ?? '—';

        return "Room {$room} ({$clash->reservationRoom->reservation->confirmation_no})";
    }
}
