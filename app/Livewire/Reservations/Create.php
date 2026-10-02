<?php

declare(strict_types=1);

namespace App\Livewire\Reservations;

use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Domain\Reservations\Services\GuestStayConflicts;
use App\Domain\Tenancy\TenantContext;
use App\Models\Agent;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomOccupant;
use App\Models\RoomType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use RuntimeException;

/**
 * @property-read Collection<int, RoomType> $roomTypes
 * @property-read RoomType|null $selectedRoomType
 * @property-read Collection<int, RatePlan> $availableRatePlans
 * @property-read RatePlan|null $selectedRatePlan
 */
#[Title('New reservation')]
class Create extends Component
{
    #[Validate('required|integer|exists:guests,id')]
    public ?int $guest_id = null;

    /** Booking agent; null means walk-in. */
    public ?int $agent_id = null;

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

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'agent_id' => [
                'nullable', 'integer',
                Rule::exists('agents', 'id')
                    ->where('hotel_id', app(TenantContext::class)->id())
                    ->where('is_active', true),
            ],
        ];
    }

    /**
     * @return Collection<int, Agent>
     */
    #[Computed]
    public function agents(): Collection
    {
        return Agent::query()->where('is_active', true)->orderBy('name')->get();
    }

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

        if ($name === 'agent_id') {
            unset($this->availableRatePlans, $this->selectedRatePlan, $this->previewNightlyRate);

            if ($this->selectedRatePlan === null) {
                $this->rate_plan_id = null;
            }
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
            ->when($this->agent_id === null, fn ($q) => $q->where('is_agent_rate', false))
            ->when($arrival, fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNull('valid_from')->orWhere('valid_from', '<=', $arrival))
                ->where(fn ($q2) => $q2->whereNull('valid_to')->orWhere('valid_to', '>=', $arrival)))
            ->orderByDesc('is_agent_rate')
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

    /**
     * Where the chosen guest is already staying for these dates. Only a
     * warning: a guest may book on someone else's behalf.
     */
    #[Computed]
    public function bookerStaysElsewhere(): ?string
    {
        if ($this->guest_id === null || $this->arrival === '' || $this->departure === '') {
            return null;
        }

        try {
            $arrival = CarbonImmutable::parse($this->arrival);
            $departure = CarbonImmutable::parse($this->departure);
        } catch (\Throwable) {
            return null;
        }

        if (! $departure->greaterThan($arrival)) {
            return null;
        }

        $conflicts = app(GuestStayConflicts::class);
        $clash = $conflicts->forGuestsBetween([$this->guest_id], $arrival, $departure)->get($this->guest_id);

        return $clash instanceof RoomOccupant ? $conflicts->describe($clash) : null;
    }

    public function save(CreateReservation $action): void
    {
        $this->validate();

        if ($this->rate_plan_id !== null && $this->selectedRatePlan === null) {
            $this->addError('rate_plan_id', $this->agent_id === null
                ? __('Agent rates can only be used when an agent is the booker.')
                : __('This rate plan is not available for the selected room type and dates.'));

            return;
        }

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
                agentId: $this->agent_id,
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
