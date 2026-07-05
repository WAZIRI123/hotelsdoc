<?php

declare(strict_types=1);

namespace App\Livewire\Guests;

use App\Models\Guest;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Register Guest')]
class Register extends Component
{
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

    #[Validate('nullable|string|max:1000')]
    public string $notes = '';

    public function save(): void
    {
        $this->validate();

        $guest = Guest::query()->create([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'nationality' => $this->nationality ?: null,
            'id_type' => $this->id_type ?: null,
            'id_number' => $this->id_number ?: null,
            'address' => $this->address ?: null,
            'notes' => $this->notes ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'Guest '.$guest->fullName().' registered.');

        $this->redirectRoute('guests.list', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.guests.register');
    }
}
