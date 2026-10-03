<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Services;

use App\Domain\Folios\Actions\PostFolioCharge;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomOccupant;
use App\Models\RoomType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Shared rules for changing who sleeps in which room: capacity limits, which
 * nights a change applies to, and posting the resulting price difference to
 * the in-house folio.
 */
final class OccupancyChanges
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly RoomNightRates $rates,
        private readonly PostFolioCharge $postCharge,
    ) {}

    public function assertFits(RoomType $roomType, int $adults, int $children): void
    {
        if ($adults > $roomType->max_adults) {
            throw new RuntimeException("This room type allows at most {$roomType->max_adults} adults.");
        }

        if ($children > $roomType->max_children) {
            throw new RuntimeException("This room type allows at most {$roomType->max_children} children.");
        }

        if (($adults + $children) > $roomType->max_occupancy) {
            throw new RuntimeException("This room type sleeps at most {$roomType->max_occupancy} guests total.");
        }
    }

    /**
     * Nights a change made now applies to: the whole stay before arrival, or
     * tonight until departure once the guests are in-house.
     *
     * @return list<string> Y-m-d
     */
    public function nightsToChange(Reservation $reservation): array
    {
        if ($reservation->status === 'confirmed') {
            return $this->availability->nights($reservation->arrival, $reservation->departure);
        }

        if ($reservation->status !== 'checked_in') {
            throw new RuntimeException("Cannot change rooms or guests on a reservation in status [{$reservation->status}].");
        }

        $today = CarbonImmutable::today();
        $from = $today->lessThan($reservation->arrival) ? CarbonImmutable::instance($reservation->arrival) : $today;

        if (! $from->lessThan($reservation->departure)) {
            throw new RuntimeException('This stay has no remaining nights to change.');
        }

        return $this->availability->nights($from, $reservation->departure);
    }

    /**
     * Guests already in-house can only go to a room nobody is still sleeping
     * in; a room whose last guest has not checked out stays 'occupied' even
     * when its booking has ended.
     *
     * @param  list<string>  $nights
     */
    public function assertRoomCanBeTaken(Room $room, array $nights, Reservation $reservation): void
    {
        if ($room->isOutOfService()) {
            throw new RuntimeException("Room {$room->number} is out of service.");
        }

        if ($reservation->status === 'checked_in' && $room->status === 'occupied') {
            throw new RuntimeException("Room {$room->number} is still occupied; check the previous guest out first.");
        }

        $from = CarbonImmutable::parse($nights[0]);
        $until = CarbonImmutable::parse($nights[count($nights) - 1])->addDay();

        if (! $this->availability->isRoomAvailable($room->id, $from, $until)) {
            throw new RuntimeException("Room {$room->number} is not available for the remaining nights.");
        }
    }

    /**
     * Store the room's new head count, re-price its remaining nights and bill
     * (or credit) the difference to the in-house folio.
     *
     * @param  list<string>  $nights
     */
    public function applyHeadCount(ReservationRoom $reservationRoom, int $adults, int $children, array $nights): void
    {
        $oldAdults = $reservationRoom->adults;
        $oldChildren = $reservationRoom->children;

        $reservationRoom->update(['adults' => $adults, 'children' => $children]);

        $difference = $this->rates->applyHeadCountChange($reservationRoom, $oldAdults, $oldChildren, $nights);

        if (! $difference->isZero()) {
            $roomNumber = $reservationRoom->room->number ?? '—';
            $label = $difference->isPositive() ? 'extra guest charge' : 'guest count reduced';

            $this->postFolioCharge(
                $reservationRoom->reservation,
                $reservationRoom,
                "Room {$roomNumber} — {$label} (".count($nights).' night(s))',
                $difference,
                count($nights),
            );
        }

        $reservationRoom->reservation->syncGuestTotals();
    }

    /**
     * Make sure the room still has a main contact after someone left it,
     * preferring an adult.
     */
    public function ensurePrimary(ReservationRoom $reservationRoom): void
    {
        $active = $reservationRoom->activeOccupants()->orderBy('id')->get();

        if ($active->isEmpty() || $active->contains('is_primary', true)) {
            return;
        }

        /** @var RoomOccupant $next */
        $next = $active->first(fn (RoomOccupant $occupant): bool => ! $occupant->isChild()) ?? $active->first();
        $next->update(['is_primary' => true]);
    }

    /**
     * Post a room charge (or credit, when negative) on the stay's folio. No-op
     * before check-in, since room charges are posted in full at check-in.
     */
    public function postFolioCharge(
        Reservation $reservation,
        ReservationRoom $reservationRoom,
        string $description,
        Money $total,
        int $quantity = 1,
    ): void {
        if ($reservation->status !== 'checked_in' || $total->isZero()) {
            return;
        }

        $folio = $reservation->stay?->folio;

        if (! $folio instanceof Folio || ! $folio->acceptsCharges()) {
            throw new RuntimeException('This stay has no open folio to post the room adjustment to.');
        }

        $this->postCharge->price($folio, 'room', $reservationRoom->id, $description, $total, $quantity);
    }
}
