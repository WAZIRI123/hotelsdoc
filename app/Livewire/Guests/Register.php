<?php

declare(strict_types=1);

namespace App\Livewire\Guests;

use App\Models\Guest;
use App\Support\Countries;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Register Guest')]
class Register extends Component
{
    #[Validate('required|string|max:160')]
    public string $guest_names = '';

    #[Validate('nullable|in:male,female')]
    public string $gender = '';

    #[Validate('nullable|string|max:120')]
    public string $caller_name = '';

    #[Validate('nullable|string|max:32')]
    public string $phone = '';

    #[Validate('nullable|email|max:120')]
    public string $email = '';

    public string $nationality = '';

    #[Validate('nullable|in:passport,national_id,drivers_license')]
    public string $id_type = '';

    #[Validate('nullable|string|max:80')]
    public string $id_number = '';

    #[Validate('nullable|string|max:500')]
    public string $address = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'nationality' => ['nullable', 'string', Rule::in(Countries::all())],
        ];
    }

    public function save(): void
    {
        $this->validate();

        [$firstName, $lastName] = array_pad(preg_split('/\s+/', trim($this->guest_names), 2), 2, null);

        $guest = Guest::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $this->gender ?: null,
            'caller_name' => $this->caller_name ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'nationality' => $this->nationality ?: null,
            'id_type' => $this->id_type ?: null,
            'id_number' => $this->id_type !== '' && $this->id_number !== '' ? $this->id_number : null,
            'address' => $this->address ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'Guest '.$guest->fullName().' registered.');

        $this->redirectRoute('guests.list', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.guests.register', [
            'countries' => Countries::all(),
        ]);
    }
}
