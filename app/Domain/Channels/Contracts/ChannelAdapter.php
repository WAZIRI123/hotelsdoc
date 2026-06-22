<?php

declare(strict_types=1);

namespace App\Domain\Channels\Contracts;

use App\Models\Room;
use Illuminate\Support\Carbon;

/**
 * Adapter contract for OTAs (Booking.com, Expedia, Airbnb).
 *
 * No implementations in MVP. The contract exists now so the canonical inventory
 * source (room_availability) and reservation source-tracking are wired correctly,
 * and a future BookingDotComAdapter is a pure addition.
 */
interface ChannelAdapter
{
    public function code(): string;

    public function pushAvailability(Room $room, Carbon $from, Carbon $to): void;

    /**
     * @return iterable<array<string, mixed>>
     */
    public function pullReservations(): iterable;
}
