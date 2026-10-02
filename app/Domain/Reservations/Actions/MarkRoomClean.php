<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Models\Room;
use RuntimeException;

/**
 * Housekeeping has finished a room left for cleaning at check-out or after a
 * room move, so it goes back on sale.
 */
final class MarkRoomClean
{
    public function execute(Room $room): Room
    {
        if ($room->status !== 'cleaning') {
            throw new RuntimeException("Room {$room->number} is not waiting for cleaning.");
        }

        $room->update(['status' => 'available']);

        return $room;
    }
}
