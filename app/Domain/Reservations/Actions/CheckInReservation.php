<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\Stay;
use App\Models\User;
use App\Support\Money;
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
    public function __construct(private readonly AvailabilityChecker $availability) {}

    public function execute(Reservation $reservation, ?User $actor = null): Stay
    {
        return DB::transaction(function () use ($reservation, $actor): Stay {
            $reservation->loadMissing('rooms.room', 'rooms.nights');

            if ($reservation->status === 'checked_in') {
                throw new RuntimeException('Reservation is already checked in.');
            }

            if (! in_array($reservation->status, ['confirmed', 'hold'], true)) {
                throw new RuntimeException("Cannot check in reservation in status [{$reservation->status}].");
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
            $subtotal = Money::zero();

            foreach ($reservation->rooms as $reservationRoom) {
                if ($reservationRoom->room === null) {
                    throw new RuntimeException('Reservation has a room slot with no assigned room.');
                }

                $lineTotal = $reservationRoom->nights->isNotEmpty()
                    ? $reservationRoom->totalRate()
                    : $reservationRoom->nightly_rate->multiply($nightCount);

                $subtotal = $subtotal->add($lineTotal);

                $folio->charges()->create([
                    'source_type' => 'room',
                    'source_id' => $reservationRoom->id,
                    'description' => "Room {$reservationRoom->room->number} — {$nightCount} night(s)",
                    'quantity' => $nightCount,
                    'unit_price' => $reservationRoom->nightly_rate,
                    'total' => $lineTotal,
                    'posted_at' => now(),
                ]);

                $reservationRoom->room->update(['status' => 'occupied']);
            }

            $folio->update([
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
            ]);

            $reservation->update(['status' => 'checked_in']);

            return $stay;
        });
    }
}
