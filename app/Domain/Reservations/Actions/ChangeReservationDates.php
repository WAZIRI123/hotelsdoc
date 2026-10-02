<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Domain\Reservations\Services\OccupancyChanges;
use App\Domain\Reservations\Services\RoomNightRates;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\ReservationRoomNight;
use App\Models\RoomAvailability;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves or resizes a stay. Before check-in both dates can change; once the
 * guests are in-house only the departure can, to extend or shorten the stay.
 *
 * Nights that are kept keep their agreed rate. Added nights are priced on the
 * room's rate plan, or at its booked nightly rate when it has none. For an
 * in-house stay the added nights are billed, and dropped nights credited, on
 * the folio.
 */
final class ChangeReservationDates
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly RoomNightRates $rates,
        private readonly OccupancyChanges $occupancy,
    ) {}

    public function execute(Reservation $reservation, CarbonInterface $arrival, CarbonInterface $departure): Reservation
    {
        return DB::transaction(function () use ($reservation, $arrival, $departure): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $reservation->load('rooms.room', 'rooms.roomType', 'rooms.ratePlan', 'rooms.nights', 'stay.folio');

            $arrival = CarbonImmutable::instance($arrival)->startOfDay();
            $departure = CarbonImmutable::instance($departure)->startOfDay();
            $this->assertAllowed($reservation, $arrival, $departure);

            $oldNights = $this->availability->nights($reservation->arrival, $reservation->departure);
            $newNights = $this->availability->nights($arrival, $departure);
            $removed = array_values(array_diff($oldNights, $newNights));
            $added = array_values(array_diff($newNights, $oldNights));

            foreach ($reservation->rooms as $reservationRoom) {
                $this->changeRoomNights($reservation, $reservationRoom, $oldNights, $removed, $added, $newNights[0]);
            }

            $reservation->update(['arrival' => $arrival, 'departure' => $departure]);

            return $reservation->fresh() ?? $reservation;
        });
    }

    private function assertAllowed(Reservation $reservation, CarbonImmutable $arrival, CarbonImmutable $departure): void
    {
        if (! $departure->isAfter($arrival)) {
            throw new RuntimeException('Departure must be after arrival.');
        }

        if ($reservation->status === 'checked_in') {
            if (! $arrival->equalTo(CarbonImmutable::instance($reservation->arrival)->startOfDay())) {
                throw new RuntimeException('The arrival date cannot change once the guests have checked in.');
            }

            if (! $departure->isAfter(CarbonImmutable::today())) {
                throw new RuntimeException('To end the stay today, check the guests out.');
            }

            return;
        }

        if ($reservation->status !== 'confirmed') {
            throw new RuntimeException("Cannot change the dates of a reservation in status [{$reservation->status}].");
        }

        if ($arrival->isBefore(CarbonImmutable::today())) {
            throw new RuntimeException('The arrival date cannot be in the past.');
        }
    }

    /**
     * @param  list<string>  $oldNights
     * @param  list<string>  $removed
     * @param  list<string>  $added
     */
    private function changeRoomNights(
        Reservation $reservation,
        ReservationRoom $reservationRoom,
        array $oldNights,
        array $removed,
        array $added,
        string $firstNight,
    ): void {
        $room = $reservationRoom->room
            ?? throw new RuntimeException('Reservation has a room slot with no assigned room.');

        // Rooms booked before per-night rates were kept get their nights filled in first.
        if ($reservationRoom->nights->isEmpty()) {
            foreach ($oldNights as $night) {
                $reservationRoom->nights()->create(['date' => $night, 'rate' => $reservationRoom->nightly_rate]);
            }
            $reservationRoom->load('nights');
        }

        if ($added !== []) {
            if ($room->isOutOfService()) {
                throw new RuntimeException("Room {$room->number} is out of service.");
            }

            $taken = RoomAvailability::query()
                ->where('room_id', $room->id)
                ->whereIn('date', $added)
                ->whereIn('status', ['booked', 'blocked'])
                ->where(fn ($query) => $query->whereNull('reservation_id')->orWhere('reservation_id', '!=', $reservation->id))
                ->exists();

            if ($taken) {
                throw new RuntimeException("Room {$room->number} is not available for the new dates.");
            }
        }

        $ratePlan = $reservationRoom->ratePlan?->room_type_id === $reservationRoom->room_type_id
            ? $reservationRoom->ratePlan
            : null;
        $addedRates = $added === [] ? [] : ($ratePlan !== null
            ? $this->rates->forStay($ratePlan, null, $reservationRoom->roomType, $reservationRoom->adults, $reservationRoom->children, $added)
            : array_fill_keys($added, $reservationRoom->nightly_rate));

        $removedNights = $reservationRoom->nights
            ->filter(fn (ReservationRoomNight $night): bool => in_array($night->date->toDateString(), $removed, true));

        $difference = $removedNights->reduce(
            fn (Money $carry, ReservationRoomNight $night): Money => $carry->subtract($night->rate),
            Money::zero(),
        );

        ReservationRoomNight::query()->whereKey($removedNights->modelKeys())->delete();
        RoomAvailability::query()
            ->where('reservation_id', $reservation->id)
            ->where('room_id', $room->id)
            ->whereIn('date', $removed)
            ->delete();

        foreach ($addedRates as $night => $rate) {
            $reservationRoom->nights()->create(['date' => $night, 'rate' => $rate]);
            RoomAvailability::query()->updateOrCreate(
                ['room_id' => $room->id, 'date' => $night],
                ['status' => 'booked', 'reservation_id' => $reservation->id],
            );
            $difference = $difference->add($rate);
        }

        $firstNightRow = $reservationRoom->nights()->whereDate('date', $firstNight)->first();
        if ($firstNightRow !== null) {
            $reservationRoom->update(['nightly_rate' => $firstNightRow->rate]);
        }

        $changed = count($added) + count($removed);
        if ($changed > 0) {
            $label = count($added) > 0 && count($removed) === 0 ? 'stay extended' : (count($removed) > 0 && count($added) === 0 ? 'stay shortened' : 'dates changed');

            $this->occupancy->postFolioCharge(
                $reservation,
                $reservationRoom,
                "Room {$room->number} — {$label} ({$changed} night(s))",
                $difference,
                $changed,
            );
        }
    }
}
