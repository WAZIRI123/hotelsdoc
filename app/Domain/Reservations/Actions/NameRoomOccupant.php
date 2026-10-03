<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\Services\GuestStayConflicts;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\RoomOccupant;
use App\Models\Stay;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Links an occupant (typically an unnamed placeholder) to a registered guest,
 * or corrects the name given to them. A name-only guest that exists solely
 * for this occupant is renamed in place or discarded when replaced; a guest
 * with any other history (the booker included) is never altered here, so
 * replacing the booker as a room's occupant links a different guest.
 */
final class NameRoomOccupant
{
    public function __construct(private readonly GuestStayConflicts $conflicts) {}

    public function execute(RoomOccupant $occupant, int $guestId): RoomOccupant
    {
        $occupant->loadMissing('reservationRoom.reservation', 'guest');

        if ($occupant->guest_id === $guestId) {
            return $occupant;
        }

        if (! Guest::query()->whereKey($guestId)->exists()) {
            throw new RuntimeException('Unknown guest.');
        }

        $alreadyInHouse = $occupant->reservationRoom->reservation->occupants()
            ->whereNull('room_occupants.checked_out_at')
            ->where('room_occupants.guest_id', $guestId)
            ->where('room_occupants.id', '!=', $occupant->id)
            ->exists();

        if ($alreadyInHouse) {
            throw new RuntimeException('This guest is already staying in a room on this reservation.');
        }

        $this->conflicts->assertNotStayingElsewhere($occupant->reservationRoom->reservation, $guestId);

        return DB::transaction(function () use ($occupant, $guestId): RoomOccupant {
            $previousGuest = $occupant->guest;
            $discardPrevious = $previousGuest !== null && $this->isOnlyKnownFrom($previousGuest, $occupant);

            $occupant->update(['guest_id' => $guestId]);

            if ($discardPrevious) {
                $previousGuest->delete();
            }

            return $occupant->unsetRelation('guest');
        });
    }

    /**
     * Names the occupant from typed text (e.g. "John Mushi"). Registers a
     * name-only guest, or fixes the spelling when the current guest exists
     * only for this occupant. ID details can be completed on the guest later.
     */
    public function executeWithNewGuest(RoomOccupant $occupant, string $fullName, ?string $gender = null): RoomOccupant
    {
        $fullName = trim($fullName);

        if ($fullName === '') {
            throw new InvalidArgumentException('A name is required.');
        }

        if ($gender !== null && ! in_array($gender, Guest::GENDERS, true)) {
            throw new InvalidArgumentException('Unknown gender.');
        }

        $occupant->loadMissing('reservationRoom.reservation', 'guest');

        [$firstName, $lastName] = array_pad(preg_split('/\s+/', $fullName, 2) ?: [], 2, null);
        $name = ['first_name' => $firstName, 'last_name' => $lastName, 'gender' => $gender];

        return DB::transaction(function () use ($occupant, $name): RoomOccupant {
            $currentGuest = $occupant->guest;

            if ($currentGuest !== null && $this->isOnlyKnownFrom($currentGuest, $occupant)) {
                $currentGuest->update($name);

                return $occupant;
            }

            return $this->execute($occupant, Guest::query()->create($name)->id);
        });
    }

    /**
     * Whether the guest is a name-only record that nothing but this occupant
     * row refers to. Guests registered with contact or ID details are kept.
     */
    private function isOnlyKnownFrom(Guest $guest, RoomOccupant $occupant): bool
    {
        $hasDetails = collect(['caller_name', 'phone', 'email', 'nationality', 'id_type', 'id_number', 'address', 'notes'])
            ->contains(fn (string $field): bool => filled($guest->getAttribute($field)));

        return ! $hasDetails
            && ! $guest->reservations()->exists()
            && ! Stay::query()->where('guest_id', $guest->id)->exists()
            && ! Folio::query()->where('guest_id', $guest->id)->exists()
            && ! RoomOccupant::query()->where('guest_id', $guest->id)->whereKeyNot($occupant->id)->exists();
    }
}
