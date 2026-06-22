<?php

declare(strict_types=1);

namespace App\Livewire\Reservations;

use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use RuntimeException;

#[Title('New reservation')]
class Create extends Component
{
    #[Validate('required|integer|exists:guests,id')]
    public ?int $guest_id = null;

    #[Validate('required|integer|exists:room_types,id')]
    public ?int $room_type_id = null;

    #[Validate('required|integer|exists:rooms,id')]
    public ?int $room_id = null;

    #[Validate('required|date')]
    public string $arrival = '';

    #[Validate('required|date|after:arrival')]
    public string $departure = '';

    #[Validate('required|integer|min:1|max:10')]
    public int $adults = 1;

    #[Validate('required|integer|min:0|max:10')]
    public int $children = 0;

    #[Validate('nullable|string|max:500')]
    public string $notes = '';

    public function mount(): void
    {
        $this->arrival = CarbonImmutable::today()->toDateString();
        $this->departure = CarbonImmutable::today()->addDay()->toDateString();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['room_type_id', 'arrival', 'departure'], true)) {
            $this->room_id = null;
            unset($this->availableRooms);
        }
    }

    /**
     * @return Collection<int, RoomType>
     */
    #[Computed]
    public function roomTypes(): Collection
    {
        return RoomType::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function availableRooms(): Collection
    {
        if ($this->room_type_id === null || $this->arrival === '' || $this->departure === '') {
            return new Collection;
        }

        try {
            $arrival = CarbonImmutable::parse($this->arrival);
            $departure = CarbonImmutable::parse($this->departure);
        } catch (\Throwable) {
            return new Collection;
        }

        if (! $departure->greaterThan($arrival)) {
            return new Collection;
        }

        return app(AvailabilityChecker::class)
            ->availableRooms($this->room_type_id, $arrival, $departure);
    }

    public function save(CreateReservation $action): void
    {
        $this->validate();

        try {
            $reservation = $action->execute(
                guestId: (int) $this->guest_id,
                roomTypeId: (int) $this->room_type_id,
                roomId: (int) $this->room_id,
                arrival: CarbonImmutable::parse($this->arrival),
                departure: CarbonImmutable::parse($this->departure),
                adults: $this->adults,
                children: $this->children,
                notes: $this->notes ?: null,
            );
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Reservation '.$reservation->confirmation_no.' created.');
        $this->redirectRoute('reservations.show', $reservation, navigate: true);
    }

    public function selectGuest(?int $id): void
    {
        $this->guest_id = $id;
    }

    #[On('guest-selected')]
    public function onGuestSelected(?int $id): void
    {
        $this->selectGuest($id);
    }

    public function render(): View
    {
        return view('livewire.reservations.create');
    }
}
