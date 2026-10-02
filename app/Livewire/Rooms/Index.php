<?php

declare(strict_types=1);

namespace App\Livewire\Rooms;

use App\Domain\Reservations\Actions\MarkRoomClean;
use App\Domain\Tenancy\TenantContext;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use RuntimeException;

#[Title('Rooms')]
class Index extends Component
{
    public ?int $editingId = null;

    #[Validate('required|integer|exists:room_types,id')]
    public ?int $room_type_id = null;

    #[Validate('required|string|max:20')]
    public string $number = '';

    public string $status = 'available';

    /** The room being edited has guests in it, so its status is not editable. */
    public bool $isOccupied = false;

    #[Validate('nullable|string|max:500')]
    public string $notes = '';

    public bool $is_active = true;

    public string $filterStatus = 'all';

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()
            ->with('roomType')
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy('number')
            ->get();
    }

    /**
     * @return Collection<int, RoomType>
     */
    #[Computed]
    public function roomTypes(): Collection
    {
        return RoomType::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'number', 'notes', 'room_type_id', 'isOccupied']);
        $this->status = 'available';
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('room-form')->show();
    }

    public function openEdit(int $id): void
    {
        $room = Room::query()->findOrFail($id);
        $this->editingId = $room->id;
        $this->room_type_id = $room->room_type_id;
        $this->number = $room->number;
        $this->status = $room->status;
        $this->isOccupied = $room->status === 'occupied';
        $this->notes = $room->notes ?? '';
        $this->is_active = $room->is_active;
        $this->resetValidation();
        Flux::modal('room-form')->show();
    }

    public function save(): void
    {
        $this->validate([
            'number' => [
                'required', 'string', 'max:20',
                Rule::unique('rooms', 'number')
                    ->where('hotel_id', app(TenantContext::class)->id())
                    ->ignore($this->editingId),
            ],
        ]);

        $room = $this->editingId ? Room::query()->findOrFail($this->editingId) : null;
        $isOccupied = $room?->status === 'occupied';

        if (! $isOccupied) {
            $this->validate(['status' => ['required', Rule::in(Room::MANUAL_STATUSES)]]);
        }

        $attributes = [
            'room_type_id' => $this->room_type_id,
            'number' => $this->number,
            // Guests are in the room: only check-out or a room move changes that.
            'status' => $isOccupied ? 'occupied' : $this->status,
            'notes' => $this->notes ?: null,
            'is_active' => $this->is_active,
        ];

        if ($room !== null) {
            $room->update($attributes);
            Flux::toast(variant: 'success', text: 'Room updated.');
        } else {
            $room = Room::query()->create($attributes);
            Flux::toast(variant: 'success', text: 'Room created.');
        }

        $this->warnAboutUpcomingBookings($room);

        Flux::modal('room-form')->close();
        unset($this->rooms);
    }

    /**
     * Housekeeping has finished: a room left for cleaning is ready to sell again.
     */
    public function markClean(int $id, MarkRoomClean $markRoomClean): void
    {
        try {
            $room = $markRoomClean->execute(Room::query()->findOrFail($id));
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: "Room {$room->number} is clean and available.");
        unset($this->rooms);
    }

    /**
     * An out-of-service room is refused at check-in, so flag bookings that
     * still need moving to another room.
     */
    private function warnAboutUpcomingBookings(Room $room): void
    {
        if (! $room->isOutOfService()) {
            return;
        }

        $bookings = RoomAvailability::query()
            ->where('room_id', $room->id)
            ->where('status', 'booked')
            ->whereDate('date', '>=', today())
            ->distinct()
            ->count('reservation_id');

        if ($bookings > 0) {
            Flux::toast(
                variant: 'warning',
                text: "Room {$room->number} is out of service but has {$bookings} upcoming booking(s). Move those guests to another room before they check in.",
                duration: 10000,
            );
        }
    }

    public function delete(int $id): void
    {
        Room::query()->findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: 'Room deleted.');
        unset($this->rooms);
    }

    public function render(): View
    {
        return view('livewire.rooms.index');
    }
}
