<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Models\Stay;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Closes a stay: marks folio closed (or settled if fully paid), flips assigned
 * rooms to 'cleaning' so housekeeping can turn them around, and stamps the
 * reservation as checked_out.
 *
 * Settling unpaid balance is a Phase 3 concern — for now we allow check-out
 * with an outstanding balance and the folio just stays 'closed'.
 */
final class CheckOutStay
{
    public function execute(Stay $stay, ?User $actor = null): Stay
    {
        return DB::transaction(function () use ($stay, $actor): Stay {
            if ($stay->checked_out_at !== null) {
                throw new RuntimeException('Stay is already checked out.');
            }

            $stay->load(['reservation.rooms.room', 'folio']);

            $folio = $stay->folio;
            if ($folio === null) {
                throw new RuntimeException('Stay has no folio. This is a data integrity error.');
            }

            $fullyPaid = $folio->paid_total->amount >= $folio->total_amount->amount;

            $folio->update([
                'status' => $fullyPaid ? 'settled' : 'closed',
                'closed_at' => now(),
                'settled_at' => $fullyPaid ? now() : null,
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
}
