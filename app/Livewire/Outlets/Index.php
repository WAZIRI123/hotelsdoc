<?php

declare(strict_types=1);

namespace App\Livewire\Outlets;

use App\Models\Outlet;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Outlets')]
class Index extends Component
{
    public ?int $editingId = null;

    #[Validate('required|string|max:80')]
    public string $name = '';

    #[Validate('required|in:bar,restaurant,room_service,shop')]
    public string $type = 'bar';

    public bool $is_active = true;

    /**
     * @return Collection<int, Outlet>
     */
    #[Computed]
    public function outlets(): Collection
    {
        return Outlet::query()->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name']);
        $this->type = 'bar';
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('outlet-form')->show();
    }

    public function openEdit(int $id): void
    {
        $outlet = Outlet::query()->findOrFail($id);
        $this->editingId = $outlet->id;
        $this->name = $outlet->name;
        $this->type = $outlet->type;
        $this->is_active = $outlet->is_active;
        $this->resetValidation();
        Flux::modal('outlet-form')->show();
    }

    public function save(): void
    {
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'type' => $this->type,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Outlet::query()->findOrFail($this->editingId)->update($attributes);
            Flux::toast(variant: 'success', text: 'Outlet updated.');
        } else {
            Outlet::query()->create($attributes);
            Flux::toast(variant: 'success', text: 'Outlet created.');
        }

        Flux::modal('outlet-form')->close();
        unset($this->outlets);
    }

    public function render(): View
    {
        return view('livewire.outlets.index');
    }
}
