<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Folios\Actions\PostFolioCharge;
use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Models\Folio;
use App\Models\ReservationRoomNight;
use App\Models\RoomAvailability;
use App\Models\Stay;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Closes a stay: marks folio closed (or settled if fully paid), flips assigned
 * rooms to 'cleaning' so housekeeping can turn them around, and stamps the
 * reservation as checked_out.
 *
 * Leaving before the booked departure frees the remaining nights for sale and
 * credits them back to the folio (room nights are billed in full at check-in);
 * the guest is always charged at least one night.
 *
 * Settling unpaid balance is a Phase 3 concern — for now we allow check-out
 * with an outstanding balance and the folio just stays 'closed'.
 */
final class CheckOutStay
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly PostFolioCharge $postCharge,
    ) {}

    public function execute(Stay $stay, ?User $actor = null): Stay
    {
        return DB::transaction(function () use ($stay, $actor): Stay {
            if ($stay->checked_out_at !== null) {
                throw new RuntimeException('Stay is already checked out.');
            }

            $stay->load(['reservation.rooms.room', 'reservation.rooms.nights', 'folio']);

            $folio = $stay->folio;
            if ($folio === null) {
                throw new RuntimeException('Stay has no folio. This is a data integrity error.');
            }

            $this->releaseUnusedNights($stay, $folio);

            $fullyPaid = $folio->paid_total->amount >= $folio->total_amount->amount;

            $folio->update([
                'status' => $fullyPaid ? 'settled' : 'closed',
                'closed_at' => now(),
                'settled_at' => $fullyPaid ? ($folio->settled_at ?? now()) : null,
            ]);

            foreach ($stay->reservation->rooms as $reservationRoom) {
                $reservationRoom->room?->update(['status' => 'cleaning']);
                $reservationRoom->activeOccupants()->update([
                    'checked_out_at' => now(),
                    'removed_by' => $actor?->id,
                ]);
            }

            $stay->update([
                'checked_out_at' => now(),
                'checked_out_by' => $actor?->id,
            ]);

            $stay->reservation->update(['status' => 'checked_out']);

            return $stay->fresh();
        });
    }

    /**
     * On an early departure, credit the nights not slept, drop them from the
     * booking and free the rooms for those nights.
     */
    private function releaseUnusedNights(Stay $stay, Folio $folio): void
    {
        $reservation = $stay->reservation;
        $arrival = CarbonImmutable::instance($reservation->arrival)->startOfDay();
        $departure = CarbonImmutable::instance($reservation->departure)->startOfDay();
        $newDeparture = CarbonImmutable::today()->max($arrival->addDay());

        if (! $newDeparture->lessThan($departure)) {
            return;
        }

        $unusedNights = $this->availability->nights($newDeparture, $departure);

        foreach ($reservation->rooms as $reservationRoom) {
            $nights = $reservationRoom->nights
                ->filter(fn (ReservationRoomNight $night): bool => in_array($night->date->toDateString(), $unusedNights, true));

            $credit = $reservationRoom->nights->isEmpty()
                ? $reservationRoom->nightly_rate->multiply(count($unusedNights))
                : $nights->reduce(fn (Money $carry, ReservationRoomNight $night): Money => $carry->add($night->rate), Money::zero());

            if ($credit->isPositive()) {
                $this->postCharge->price(
                    folio: $folio,
                    sourceType: 'room',
                    sourceId: $reservationRoom->id,
                    description: 'Room '.($reservationRoom->room->number ?? '—').' — early check-out, '.count($unusedNights).' night(s) not used',
                    price: Money::zero($credit->currency)->subtract($credit),
                    quantity: count($unusedNights),
                );
            }

            ReservationRoomNight::query()->whereKey($nights->modelKeys())->delete();
        }

        RoomAvailability::query()
            ->where('reservation_id', $reservation->id)
            ->whereIn('date', $unusedNights)
            ->delete();

        $reservation->update(['departure' => $newDeparture]);
    }
}
