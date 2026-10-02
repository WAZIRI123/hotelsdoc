<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Models\Reservation;
use App\Models\RoomAvailability;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CancelReservation
{
    public function execute(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            if ($reservation->status !== 'confirmed') {
                throw new RuntimeException(
                    "Cannot cancel a reservation in status [{$reservation->status}].",
                );
            }

            RoomAvailability::query()
                ->where('reservation_id', $reservation->id)
                ->delete();

            $reservation->update(['status' => 'cancelled']);

            return $reservation->fresh();
        });
    }
}
