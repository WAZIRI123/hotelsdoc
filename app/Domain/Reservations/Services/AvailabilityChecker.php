<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Services;

use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomAvailability;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Resolves which rooms are bookable for a given date range.
 *
 * Source of truth is the room_availability table — one row per (room, date).
 * A row with status='booked' or 'blocked' makes that night unavailable.
 * Rooms that are out of service (inactive, maintenance, out of order) are
 * never available, whatever the dates.
 *
 * A guest still checked in after their departure date keeps the room: it is
 * not offered for tonight until they are checked out or their stay extended.
 *
 * Range semantics: arrival is inclusive, departure is exclusive (industry standard
 * "night of arrival" — checking out on the 5th means you slept the 4th but not the 5th).
 */
final class AvailabilityChecker
{
    /**
     * @param  int|null  $roomTypeId  null searches every room type
     * @return Collection<int, Room>
     */
    public function availableRooms(
        ?int $roomTypeId,
        CarbonInterface $arrival,
        CarbonInterface $departure,
        ?int $excludeReservationId = null,
    ): Collection {
        $nights = $this->nights($arrival, $departure);

        $bookedRoomIds = RoomAvailability::query()
            ->whereIn('date', $nights)
            ->whereIn('status', ['booked', 'blocked'])
            ->when($excludeReservationId, fn ($q, $id) => $q->where(function ($q) use ($id): void {
                $q->whereNull('reservation_id')->orWhere('reservation_id', '!=', $id);
            }))
            ->pluck('room_id')
            ->merge($this->overstayRoomIds($nights, $excludeReservationId))
            ->unique()
            ->all();

        return Room::query()
            ->when($roomTypeId, fn ($q, int $id) => $q->where('room_type_id', $id))
            ->where('is_active', true)
            ->whereNotIn('status', Room::OUT_OF_SERVICE_STATUSES)
            ->whereNotIn('id', $bookedRoomIds)
            ->orderBy('number')
            ->get();
    }

    public function isRoomAvailable(
        int $roomId,
        CarbonInterface $arrival,
        CarbonInterface $departure,
        ?int $excludeReservationId = null,
    ): bool {
        $nights = $this->nights($arrival, $departure);

        if (Room::query()->find($roomId)?->isOutOfService() ?? true) {
            return false;
        }

        if (in_array($roomId, $this->overstayRoomIds($nights, $excludeReservationId), true)) {
            return false;
        }

        return ! RoomAvailability::query()
            ->where('room_id', $roomId)
            ->whereIn('date', $nights)
            ->whereIn('status', ['booked', 'blocked'])
            ->when($excludeReservationId, fn ($q, $id) => $q->where(function ($q) use ($id): void {
                $q->whereNull('reservation_id')->orWhere('reservation_id', '!=', $id);
            }))
            ->exists();
    }

    /**
     * Rooms held by guests still checked in after their departure date, when
     * the requested nights include tonight. Those nights were never booked,
     * so room_availability alone would sell the room twice.
     *
     * @param  list<string>  $nights
     * @return list<int>
     */
    private function overstayRoomIds(array $nights, ?int $excludeReservationId): array
    {
        if ($nights[0] > CarbonImmutable::today()->toDateString()) {
            return [];
        }

        return ReservationRoom::query()
            ->whereNotNull('room_id')
            ->whereHas('reservation', fn ($query) => $query
                ->overdueDeparture()
                ->when($excludeReservationId, fn ($query, int $id) => $query->whereKeyNot($id)))
            ->pluck('room_id')
            ->all();
    }

    /**
     * Expand a date range into the list of nights (inclusive arrival, exclusive departure).
     *
     * @return list<string> Y-m-d strings, suitable for whereIn() on a DATE column.
     */
    public function nights(CarbonInterface $arrival, CarbonInterface $departure): array
    {
        $a = CarbonImmutable::instance($arrival)->startOfDay();
        $d = CarbonImmutable::instance($departure)->startOfDay();

        if (! $d->greaterThan($a)) {
            throw new InvalidArgumentException('Departure must be after arrival.');
        }

        $nights = [];
        for ($cursor = $a; $cursor->lessThan($d); $cursor = $cursor->addDay()) {
            $nights[] = $cursor->toDateString();
        }

        return $nights;
    }
}
