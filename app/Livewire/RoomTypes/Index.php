<?php

declare(strict_types=1);

namespace App\Livewire\RoomTypes;

use App\Domain\Tenancy\TenantContext;
use App\Models\RoomType;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Room types')]
class Index extends Component
{
    public ?int $editingId = null;

    #[Validate('required|string|max:10')]
    public string $code = '';

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('nullable|string|max:500')]
    public string $description = '';

    #[Validate('required|integer|min:1|max:20')]
    public int $base_occupancy = 2;

    #[Validate('required|integer|min:1|max:20')]
    public int $max_occupancy = 2;

    /** Price in whole TZS (no decimals). */
    #[Validate('required|integer|min:0')]
    public int $default_rate_tsh = 0;

    public bool $is_active = true;

    /**
     * @return Collection<int, RoomType>
     */
    #[Computed]
    public function roomTypes(): Collection
    {
        return RoomType::query()
            ->orderBy('name')
            ->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'code', 'name', 'description', 'default_rate_tsh']);
        $this->base_occupancy = 2;
        $this->max_occupancy = 2;
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('room-type-form')->show();
    }

    public function openEdit(int $id): void
    {
        $type = RoomType::query()->findOrFail($id);
        $this->editingId = $type->id;
        $this->code = $type->code;
        $this->name = $type->name;
        $this->description = $type->description ?? '';
        $this->base_occupancy = $type->base_occupancy;
        $this->max_occupancy = $type->max_occupancy;
        $this->default_rate_tsh = (int) $type->default_rate->toMajor();
        $this->is_active = $type->is_active;
        $this->resetValidation();
        Flux::modal('room-type-form')->show();
    }

    public function save(): void
    {
        $this->validate([
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('room_types', 'code')
                    ->where('hotel_id', app(TenantContext::class)->id())
                    ->ignore($this->editingId),
            ],
        ]);

        if ($this->max_occupancy < $this->base_occupancy) {
            $this->addError('max_occupancy', 'Max occupancy cannot be less than base occupancy.');

            return;
        }

        $attributes = [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'base_occupancy' => $this->base_occupancy,
            'max_occupancy' => $this->max_occupancy,
            'default_rate' => Money::fromMajor($this->default_rate_tsh),
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            RoomType::query()->findOrFail($this->editingId)->update($attributes);
            Flux::toast(variant: 'success', text: 'Room type updated.');
        } else {
            RoomType::query()->create($attributes);
            Flux::toast(variant: 'success', text: 'Room type created.');
        }

        Flux::modal('room-type-form')->close();
        unset($this->roomTypes);
    }

    public function delete(int $id): void
    {
        $roomType = RoomType::query()->findOrFail($id);

        if ($roomType->isInUse()) {
            $roomType->update(['is_active' => false]);
            Flux::toast(text: __('This room type has rooms or bookings, so it was deactivated instead of deleted.'));
        } else {
            $roomType->delete();
            Flux::toast(variant: 'success', text: 'Room type deleted.');
        }
        unset($this->roomTypes);
    }

    public function render(): View
    {
        return view('livewire.room-types.index');
    }
}
