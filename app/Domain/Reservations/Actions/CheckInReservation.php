<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Folios\Actions\PostFolioCharge;
use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\Stay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Transitions a confirmed reservation into an active stay.
 *
 * Creates the Stay, opens a Folio, posts a single charge per assigned room
 * covering the full stay (room nights × nightly rate), and flips the room
 * status to 'occupied'.
 *
 * Nightly posting (one charge per night via a scheduled job) is a Phase 3 concern
 * — for MVP we post up-front so the folio total reflects the room cost immediately.
 */
final class CheckInReservation
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly PostFolioCharge $postCharge,
    ) {}

    public function execute(Reservation $reservation, ?User $actor = null): Stay
    {
        return DB::transaction(function () use ($reservation, $actor): Stay {
            // Lock the booking so two clicks cannot open two stays and bill the rooms twice.
            Reservation::query()->whereKey($reservation->id)->lockForUpdate()->first();
            $reservation->refresh()->loadMissing('rooms.room', 'rooms.nights');

            if ($reservation->status === 'checked_in') {
                throw new RuntimeException('Reservation is already checked in.');
            }

            if ($reservation->status !== 'confirmed') {
                throw new RuntimeException("Cannot check in reservation in status [{$reservation->status}].");
            }

            if (CarbonImmutable::instance($reservation->arrival)->startOfDay()->isAfter(CarbonImmutable::today())) {
                throw new RuntimeException('This reservation arrives on '.$reservation->arrival->format('d M Y').'; change its dates to check in early.');
            }

            foreach ($reservation->rooms as $reservationRoom) {
                if ($reservationRoom->room?->status === 'occupied') {
                    throw new RuntimeException("Room {$reservationRoom->room->number} is still occupied; check the previous guest out first.");
                }
            }

            $stay = Stay::query()->create([
                'reservation_id' => $reservation->id,
                'guest_id' => $reservation->guest_id,
                'checked_in_at' => now(),
                'checked_in_by' => $actor?->id,
            ]);

            $folio = Folio::query()->create([
                'stay_id' => $stay->id,
                'guest_id' => $reservation->guest_id,
                'status' => 'open',
            ]);

            $nightCount = count($this->availability->nights($reservation->arrival, $reservation->departure));

            foreach ($reservation->rooms as $reservationRoom) {
                if ($reservationRoom->room === null) {
                    throw new RuntimeException('Reservation has a room slot with no assigned room.');
                }

                if ($reservationRoom->room->isOutOfService()) {
                    throw new RuntimeException("Room {$reservationRoom->room->number} is out of service; move the guests to another room before check-in.");
                }

                $lineTotal = $reservationRoom->nights->isNotEmpty()
                    ? $reservationRoom->totalRate()
                    : $reservationRoom->nightly_rate->multiply($nightCount);

                $this->postCharge->price(
                    folio: $folio,
                    sourceType: 'room',
                    sourceId: $reservationRoom->id,
                    description: "Room {$reservationRoom->room->number} — {$nightCount} night(s)",
                    price: $lineTotal,
                    quantity: $nightCount,
                );

                $reservationRoom->room->update(['status' => 'occupied']);

                $reservationRoom->activeOccupants()->update(['checked_in_at' => now()]);
            }

            $reservation->update(['status' => 'checked_in']);

            return $stay;
        });
    }
}
