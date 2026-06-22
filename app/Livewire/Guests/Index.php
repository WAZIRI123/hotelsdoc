<?php

declare(strict_types=1);

namespace App\Livewire\Guests;

use App\Models\Guest;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Guests')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $editingId = null;

    #[Validate('required|string|max:80')]
    public string $first_name = '';

    #[Validate('nullable|string|max:80')]
    public string $last_name = '';

    #[Validate('nullable|string|max:32')]
    public string $phone = '';

    #[Validate('nullable|email|max:120')]
    public string $email = '';

    #[Validate('nullable|string|max:80')]
    public string $nationality = '';

    #[Validate('nullable|in:passport,national_id,drivers_license')]
    public string $id_type = '';

    #[Validate('nullable|string|max:80')]
    public string $id_number = '';

    #[Validate('nullable|string|max:500')]
    public string $address = '';

    #[Validate('nullable|string|max:500')]
    public string $notes = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Guest>
     */
    #[Computed]
    public function guests(): LengthAwarePaginator
    {
        return Guest::query()
            ->when($this->search, function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term): void {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('first_name')
            ->paginate(15);
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'first_name', 'last_name', 'phone', 'email', 'nationality', 'id_type', 'id_number', 'address', 'notes']);
        $this->resetValidation();
        Flux::modal('guest-form')->show();
    }

    public function openEdit(int $id): void
    {
        $guest = Guest::query()->findOrFail($id);
        $this->editingId = $guest->id;
        $this->first_name = $guest->first_name;
        $this->last_name = $guest->last_name ?? '';
        $this->phone = $guest->phone ?? '';
        $this->email = $guest->email ?? '';
        $this->nationality = $guest->nationality ?? '';
        $this->id_type = $guest->id_type ?? '';
        $this->id_number = $guest->id_number ?? '';
        $this->address = $guest->address ?? '';
        $this->notes = $guest->notes ?? '';
        $this->resetValidation();
        Flux::modal('guest-form')->show();
    }

    public function save(): void
    {
        $this->validate();

        $attributes = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'nationality' => $this->nationality ?: null,
            'id_type' => $this->id_type ?: null,
            'id_number' => $this->id_number ?: null,
            'address' => $this->address ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId) {
            Guest::query()->findOrFail($this->editingId)->update($attributes);
            Flux::toast(variant: 'success', text: 'Guest updated.');
        } else {
            Guest::query()->create($attributes);
            Flux::toast(variant: 'success', text: 'Guest created.');
        }

        Flux::modal('guest-form')->close();
        unset($this->guests);
    }

    public function render(): View
    {
        return view('livewire.guests.index');
    }
}
