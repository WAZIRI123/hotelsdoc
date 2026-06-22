<?php

declare(strict_types=1);

namespace App\Livewire\Rooms;

use App\Models\Room;
use App\Models\RoomType;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Rooms')]
class Index extends Component
{
    public ?int $editingId = null;

    #[Validate('required|integer|exists:room_types,id')]
    public ?int $room_type_id = null;

    #[Validate('required|string|max:20')]
    public string $number = '';

    #[Validate('nullable|string|max:20')]
    public string $floor = '';

    #[Validate('required|in:available,occupied,cleaning,maintenance,out_of_order')]
    public string $status = 'available';

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
        $this->reset(['editingId', 'number', 'floor', 'notes', 'room_type_id']);
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
        $this->floor = $room->floor ?? '';
        $this->status = $room->status;
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
                    ->where('hotel_id', app(\App\Domain\Tenancy\TenantContext::class)->id())
                    ->ignore($this->editingId),
            ],
        ]);

        $attributes = [
            'room_type_id' => $this->room_type_id,
            'number' => $this->number,
            'floor' => $this->floor ?: null,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Room::query()->findOrFail($this->editingId)->update($attributes);
            Flux::toast(variant: 'success', text: 'Room updated.');
        } else {
            Room::query()->create($attributes);
            Flux::toast(variant: 'success', text: 'Room created.');
        }

        Flux::modal('room-form')->close();
        unset($this->rooms);
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
