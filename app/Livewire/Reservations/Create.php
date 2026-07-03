<?php

declare(strict_types=1);

namespace App\Livewire\Reservations;

use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\Money;
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

    #[Validate('nullable|integer|exists:rate_plans,id')]
    public ?int $rate_plan_id = null;

    #[Validate('required|integer|exists:rooms,id')]
    public ?int $room_id = null;

    #[Validate('required|date')]
    public string $arrival = '';

    #[Validate('required|date|after:arrival')]
    public string $departure = '';

    #[Validate('required|integer|min:1')]
    public int $adults = 1;

    #[Validate('required|integer|min:0')]
    public int $children = 0;

    #[Validate('nullable|string|max:500')]
    public string $notes = '';

    public function mount(): void
    {
        $this->arrival = CarbonImmutable::today()->toDateString();
        $this->departure = CarbonImmutable::today()->addDay()->toDateString();

        $roomId = request()->integer('room');

        if ($roomId > 0) {
            $room = Room::query()->find($roomId);

            if ($room !== null && $room->status === 'available') {
                $this->room_type_id = $room->room_type_id;
                $this->room_id = $room->id;
            }
        }
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['room_type_id', 'arrival', 'departure'], true)) {
            $this->room_id = null;
            unset($this->availableRooms);
        }

        if ($name === 'room_type_id') {
            $this->rate_plan_id = null;
            unset($this->availableRatePlans, $this->selectedRatePlan);
        }

        if (in_array($name, ['rate_plan_id', 'adults', 'children'], true)) {
            unset($this->selectedRatePlan, $this->previewNightlyRate);
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

    #[Computed]
    public function selectedRoomType(): ?RoomType
    {
        if ($this->room_type_id === null) {
            return null;
        }

        return $this->roomTypes->firstWhere('id', $this->room_type_id);
    }

    /**
     * @return Collection<int, RatePlan>
     */
    #[Computed]
    public function availableRatePlans(): Collection
    {
        if ($this->room_type_id === null) {
            return new Collection;
        }

        $arrival = $this->arrival !== '' ? CarbonImmutable::parse($this->arrival)->toDateString() : null;

        return RatePlan::query()
            ->with('mealPlan')
            ->where('room_type_id', $this->room_type_id)
            ->where('is_active', true)
            ->when($arrival, fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNull('valid_from')->orWhere('valid_from', '<=', $arrival))
                ->where(fn ($q2) => $q2->whereNull('valid_to')->orWhere('valid_to', '>=', $arrival)))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedRatePlan(): ?RatePlan
    {
        if ($this->rate_plan_id === null) {
            return null;
        }

        return $this->availableRatePlans->firstWhere('id', $this->rate_plan_id);
    }

    #[Computed]
    public function previewNightlyRate(): ?Money
    {
        $roomType = $this->selectedRoomType;
        if ($roomType === null) {
            return null;
        }

        $plan = $this->selectedRatePlan;

        if ($plan !== null) {
            $rate = $plan->price;
            $extraAdults = max(0, $this->adults - $roomType->base_occupancy);
            if ($extraAdults > 0 && $plan->extra_adult_price->isPositive()) {
                $rate = $rate->add($plan->extra_adult_price->multiply($extraAdults));
            }
            if ($this->children > 0 && $plan->extra_child_price->isPositive()) {
                $rate = $rate->add($plan->extra_child_price->multiply($this->children));
            }

            return $rate;
        }

        $rate = $roomType->default_rate;
        $extraGuests = max(0, ($this->adults + $this->children) - $roomType->base_occupancy);
        if ($extraGuests > 0 && $roomType->extra_guest_fee->isPositive()) {
            $rate = $rate->add($roomType->extra_guest_fee->multiply($extraGuests));
        }

        return $rate;
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

        $roomType = $this->selectedRoomType;
        if ($roomType !== null) {
            if ($this->adults > $roomType->max_adults) {
                $this->addError('adults', "This room type allows at most {$roomType->max_adults} adults.");

                return;
            }
            if ($this->children > $roomType->max_children) {
                $this->addError('children', "This room type allows at most {$roomType->max_children} children.");

                return;
            }
            if (($this->adults + $this->children) > $roomType->max_occupancy) {
                $this->addError('adults', "This room type sleeps at most {$roomType->max_occupancy} guests total.");

                return;
            }
        }

        try {
            $reservation = $action->execute(
                guestId: (int) $this->guest_id,
                roomTypeId: (int) $this->room_type_id,
                roomId: (int) $this->room_id,
                arrival: CarbonImmutable::parse($this->arrival),
                departure: CarbonImmutable::parse($this->departure),
                adults: $this->adults,
                children: $this->children,
                ratePlanId: $this->rate_plan_id,
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
