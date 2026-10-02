<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Models\Reservation;
use App\Models\RoomAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Marks a confirmed reservation whose guest never arrived as a no-show and
 * frees its rooms for the booked nights.
 */
final class MarkNoShow
{
    public function execute(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            if ($reservation->status !== 'confirmed') {
                throw new RuntimeException("Cannot mark a reservation in status [{$reservation->status}] as a no-show.");
            }

            if (CarbonImmutable::instance($reservation->arrival)->startOfDay()->isAfter(CarbonImmutable::today())) {
                throw new RuntimeException('The guest is not due until '.$reservation->arrival->format('d M Y').'.');
            }

            RoomAvailability::query()
                ->where('reservation_id', $reservation->id)
                ->delete();

            $reservation->update(['status' => 'no_show']);

            return $reservation->fresh() ?? $reservation;
        });
    }
}
