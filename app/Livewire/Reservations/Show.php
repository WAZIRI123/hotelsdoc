<?php

declare(strict_types=1);

namespace App\Livewire\Reservations;

use App\Domain\Reservations\Actions\AddRoomOccupant;
use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutStay;
use App\Domain\Reservations\Actions\MoveRoom;
use App\Domain\Reservations\Actions\NameRoomOccupant;
use App\Domain\Reservations\Actions\RemoveRoomOccupant;
use App\Domain\Reservations\Actions\TransferOccupant;
use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Domain\Reservations\Services\GuestStayConflicts;
use App\Domain\Reservations\Services\OccupancyChanges;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomMove;
use App\Models\RoomOccupant;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * @property-read bool $canChangeOccupancy
 * @property-read bool $canNameOccupants
 * @property-read Collection<int, Guest> $quickNameMatches
 * @property-read array<int, string> $quickNameConflicts
 * @property-read array<int, string> $occupantConflicts
 * @property-read Collection<int, Room> $roomsFreeForRemainingNights
 */
#[Title('Reservation')]
class Show extends Component
{
    public Reservation $reservation;

    /** Reserved room the inline "add person" form is open for. */
    public ?int $addingToRoomId = null;

    /** Occupant the inline "name guest" form is open for. */
    public ?int $namingOccupantId = null;

    public string $occupantAgeCategory = 'adult';

    public ?int $occupantGuestId = null;

    /** Placeholder occupant whose name is being typed inline. */
    public ?int $quickNamingOccupantId = null;

    public string $quickName = '';

    public string $quickGender = '';

    /** Bumped on every form open so the guest picker starts empty. */
    public int $pickerKey = 0;

    public ?int $movingRoomId = null;

    public ?int $moveToRoomId = null;

    public string $moveRateAction = 'keep_rate';

    public string $moveReason = '';

    public ?int $transferringOccupantId = null;

    /** "reservation-room:{id}" or "room:{id}" */
    public string $transferTarget = '';

    public function mount(Reservation $reservation): void
    {
        $this->reservation = $reservation;
        $this->reloadReservation();
    }

    public function checkIn(CheckInReservation $action): void
    {
        try {
            $action->execute($this->reservation, Auth::user());
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Checked in.');
        $this->reloadReservation();
    }

    public function checkOut(CheckOutStay $action): void
    {
        $stay = $this->reservation->stay;
        if ($stay === null) {
            Flux::toast(variant: 'danger', text: 'No active stay to check out.');

            return;
        }

        try {
            $action->execute($stay, Auth::user());
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Checked out.');
        $this->reloadReservation();
    }

    public function cancel(CancelReservation $action): void
    {
        try {
            $action->execute($this->reservation);
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Reservation cancelled.');
        $this->reloadReservation();
    }

    public function startAddingOccupant(int $reservationRoomId): void
    {
        $this->resetOccupantForm();
        $this->addingToRoomId = $this->findReservationRoom($reservationRoomId)->id;
    }

    public function startNamingOccupant(int $occupantId): void
    {
        if (! $this->canNameOccupants) {
            return;
        }

        $this->resetOccupantForm();
        $this->namingOccupantId = $this->findOccupant($occupantId)->id;
    }

    public function cancelOccupantForm(): void
    {
        $this->resetOccupantForm();
    }

    #[On('guest-selected')]
    public function onGuestSelected(?int $id): void
    {
        $this->occupantGuestId = $id;
    }

    public function saveOccupant(AddRoomOccupant $add, NameRoomOccupant $name): void
    {
        $this->validate([
            'occupantAgeCategory' => ['required', 'in:'.implode(',', RoomOccupant::AGE_CATEGORIES)],
            'occupantGuestId' => [$this->namingOccupantId !== null ? 'required' : 'nullable', 'integer'],
        ], [
            'occupantGuestId.required' => __('Pick the guest staying in this bed.'),
        ]);

        try {
            if ($this->namingOccupantId !== null) {
                if (! $this->canNameOccupants) {
                    throw new RuntimeException('Guests can no longer be named on this reservation.');
                }

                $name->execute($this->findOccupant($this->namingOccupantId), (int) $this->occupantGuestId);
                $message = 'Guest named.';
            } else {
                $add->execute(
                    $this->findReservationRoom((int) $this->addingToRoomId),
                    $this->occupantAgeCategory,
                    $this->occupantGuestId,
                    Auth::user(),
                );
                $message = 'Person added to the room.';
            }
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: $message);
        $this->resetOccupantForm();
        $this->reloadReservation();
    }

    public function startQuickNaming(int $occupantId): void
    {
        if (! $this->canNameOccupants) {
            return;
        }

        $occupant = $this->findOccupant($occupantId);
        $isBooker = $occupant->guest_id !== null && $occupant->guest_id === $this->reservation->guest_id;

        $this->resetOccupantForm();
        $this->quickNamingOccupantId = $occupant->id;
        // The booker is replaced by whoever actually sleeps here, never renamed.
        $this->quickName = $isBooker ? '' : ($occupant->guest?->fullName() ?? '');
        $this->quickGender = $isBooker ? '' : ($occupant->guest?->gender ?? '');
    }

    public function cancelQuickNaming(): void
    {
        $this->reset(['quickNamingOccupantId', 'quickName', 'quickGender']);
        $this->resetValidation(['quickName', 'quickGender']);
    }

    /**
     * Saves the typed name as a new name-only guest for the placeholder.
     */
    public function saveQuickName(NameRoomOccupant $action): void
    {
        $this->validate([
            'quickName' => ['required', 'string', 'max:255'],
            'quickGender' => ['nullable', 'in:'.implode(',', Guest::GENDERS)],
        ], [
            'quickName.required' => __('Type the guest name.'),
        ]);

        $this->nameQuickly(fn (RoomOccupant $occupant) => $action->executeWithNewGuest($occupant, $this->quickName, $this->quickGender ?: null));
    }

    /**
     * Links the placeholder to an existing guest suggested while typing.
     */
    public function pickQuickNameMatch(int $guestId, NameRoomOccupant $action): void
    {
        $guest = Guest::query()->findOrFail($guestId);

        $this->nameQuickly(fn (RoomOccupant $occupant) => $action->execute($occupant, $guest->id));
    }

    /**
     * Existing guests whose name matches what is being typed, to avoid duplicates.
     *
     * @return Collection<int, Guest>
     */
    #[Computed]
    public function quickNameMatches(): Collection
    {
        $typed = trim($this->quickName);

        if ($this->quickNamingOccupantId === null || mb_strlen($typed) < 2) {
            return new Collection;
        }

        $query = Guest::query();

        foreach (preg_split('/\s+/', $typed) ?: [] as $word) {
            $term = '%'.$word.'%';
            $query->where(fn ($q) => $q->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
        }

        $currentGuestId = RoomOccupant::query()->whereKey($this->quickNamingOccupantId)->value('guest_id');

        return $query
            ->when($currentGuestId !== null, fn ($q) => $q->whereKeyNot($currentGuestId))
            ->orderBy('first_name')
            ->limit(5)
            ->get();
    }

    /**
     * Where each suggested guest is already staying for overlapping dates,
     * keyed by guest id, so the clerk sees the clash before picking.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function quickNameConflicts(): array
    {
        $conflicts = app(GuestStayConflicts::class);

        return $conflicts
            ->forGuests($this->reservation, $this->quickNameMatches->modelKeys())
            ->map(fn (RoomOccupant $clash): string => $conflicts->describe($clash))
            ->all();
    }

    /**
     * Where people in this reservation's rooms are also staying for
     * overlapping dates, keyed by guest id. Mostly catches a booker who
     * booked on someone else's behalf.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function occupantConflicts(): array
    {
        if (! $this->canNameOccupants) {
            return [];
        }

        $guestIds = $this->reservation->rooms
            ->flatMap(fn (ReservationRoom $rr) => $rr->occupants->whereNull('checked_out_at')->pluck('guest_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $conflicts = app(GuestStayConflicts::class);

        return $conflicts
            ->forGuests($this->reservation, $guestIds)
            ->map(fn (RoomOccupant $clash): string => $conflicts->describe($clash))
            ->all();
    }

    public function removeOccupant(int $occupantId, RemoveRoomOccupant $action): void
    {
        try {
            $action->execute($this->findOccupant($occupantId), Auth::user());
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Person removed from the room.');
        $this->reloadReservation();
    }

    public function startMovingRoom(int $reservationRoomId): void
    {
        $this->reset(['moveToRoomId', 'moveRateAction', 'moveReason']);
        $this->resetValidation();
        $this->movingRoomId = $this->findReservationRoom($reservationRoomId)->id;
        unset($this->roomsFreeForRemainingNights);

        Flux::modal('move-room')->show();
    }

    public function moveRoom(MoveRoom $action): void
    {
        $this->validate([
            'moveToRoomId' => ['required', 'integer'],
            'moveRateAction' => ['required', 'in:'.implode(',', RoomMove::RATE_ACTIONS)],
            'moveReason' => ['nullable', 'string', 'max:255'],
        ], [
            'moveToRoomId.required' => __('Pick the room to move to.'),
        ]);

        try {
            $action->execute(
                $this->findReservationRoom((int) $this->movingRoomId),
                Room::query()->findOrFail($this->moveToRoomId),
                $this->moveRateAction,
                $this->moveReason !== '' ? $this->moveReason : null,
                Auth::user(),
            );
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::modal('move-room')->close();
        Flux::toast(variant: 'success', text: 'Room moved.');
        $this->movingRoomId = null;
        $this->reloadReservation();
    }

    public function startTransfer(int $occupantId): void
    {
        $this->reset('transferTarget');
        $this->resetValidation();
        $this->transferringOccupantId = $this->findOccupant($occupantId)->id;
        unset($this->roomsFreeForRemainingNights);

        Flux::modal('transfer-occupant')->show();
    }

    public function transferOccupant(TransferOccupant $action): void
    {
        $this->validate([
            'transferTarget' => ['required', 'regex:/^(reservation-room|room):\d+$/'],
        ], [
            'transferTarget.required' => __('Pick where this person is going.'),
        ]);

        [$kind, $id] = explode(':', $this->transferTarget);
        $occupant = $this->findOccupant((int) $this->transferringOccupantId);

        try {
            if ($kind === 'reservation-room') {
                $action->execute($occupant, toReservationRoom: $this->findReservationRoom((int) $id), actor: Auth::user());
            } else {
                $action->execute($occupant, toNewRoom: Room::query()->findOrFail((int) $id), actor: Auth::user());
            }
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::modal('transfer-occupant')->close();
        Flux::toast(variant: 'success', text: 'Guest transferred.');
        $this->transferringOccupantId = null;
        $this->reloadReservation();
    }

    /**
     * Whether people and rooms on this reservation can still be changed.
     */
    #[Computed]
    public function canChangeOccupancy(): bool
    {
        return in_array($this->reservation->status, ['hold', 'confirmed', 'checked_in'], true)
            && $this->reservation->departure->isAfter(CarbonImmutable::today());
    }

    /**
     * Whether placeholders can be named. Unlike head-count changes this never
     * touches charges, so it stays open for the whole stay, departure day included.
     */
    #[Computed]
    public function canNameOccupants(): bool
    {
        return in_array($this->reservation->status, ['hold', 'confirmed', 'checked_in'], true);
    }

    /**
     * Rooms free for every remaining night, for the move and transfer dialogs.
     *
     * @return Collection<int, Room>
     */
    #[Computed]
    public function roomsFreeForRemainingNights(): Collection
    {
        if (! $this->canChangeOccupancy) {
            return new Collection;
        }

        try {
            $nights = app(OccupancyChanges::class)->nightsToChange($this->reservation);
        } catch (RuntimeException) {
            return new Collection;
        }

        return app(AvailabilityChecker::class)
            ->availableRooms(null, CarbonImmutable::parse($nights[0]), $this->reservation->departure)
            ->whereNotIn('status', ['maintenance', 'out_of_order'])
            ->load('roomType')
            ->values();
    }

    public function render(): View
    {
        return view('livewire.reservations.show');
    }

    /**
     * @param  callable(RoomOccupant): RoomOccupant  $name
     */
    private function nameQuickly(callable $name): void
    {
        if (! $this->canNameOccupants) {
            return;
        }

        try {
            $name($this->findOccupant((int) $this->quickNamingOccupantId));
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Name saved.');
        $this->cancelQuickNaming();
        $this->reloadReservation();
    }

    private function resetOccupantForm(): void
    {
        $this->reset(['addingToRoomId', 'namingOccupantId', 'occupantAgeCategory', 'occupantGuestId', 'quickNamingOccupantId', 'quickName', 'quickGender']);
        $this->resetValidation();
        $this->pickerKey++;
    }

    private function reloadReservation(): void
    {
        $this->reservation->refresh()->load([
            'guest',
            'agent',
            'rooms.room',
            'rooms.roomType',
            'rooms.occupants' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
            'rooms.occupants.guest',
            'rooms.moves' => fn ($query) => $query->latest('id'),
            'rooms.moves.fromRoom',
            'rooms.moves.toRoom',
            'stay.folio.charges',
        ]);

        unset($this->canChangeOccupancy, $this->canNameOccupants, $this->occupantConflicts, $this->roomsFreeForRemainingNights);
    }

    private function findReservationRoom(int $id): ReservationRoom
    {
        return $this->reservation->rooms()->findOrFail($id);
    }

    private function findOccupant(int $id): RoomOccupant
    {
        /** @var RoomOccupant */
        return RoomOccupant::query()
            ->whereIn('reservation_room_id', $this->reservation->rooms()->select('id'))
            ->findOrFail($id);
    }
}
