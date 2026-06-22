<?php

use App\Models\Guest;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public bool $show = false;

    #[Validate('required|string|max:80')]
    public string $firstName = '';

    #[Validate('nullable|string|max:80')]
    public string $lastName = '';

    #[Validate('nullable|string|max:32')]
    public string $phone = '';

    #[Validate('nullable|email|max:120')]
    public string $email = '';

    #[Validate('nullable|string|max:80')]
    public string $nationality = '';

    #[On('new-guest-form:open')]
    public function open(string $prefillFirstName = ''): void
    {
        $this->reset(['firstName', 'lastName', 'phone', 'email', 'nationality']);
        $this->firstName = $prefillFirstName;
        $this->resetValidation();
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $guest = Guest::query()->create([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'nationality' => $this->nationality ?: null,
        ]);

        $this->reset(['firstName', 'lastName', 'phone', 'email', 'nationality']);
        $this->show = false;

        $this->dispatch('guest-created', id: $guest->id);
        Flux::toast(variant: 'success', text: 'Guest created and selected.');
    }
};
?>

<div
    x-data
    x-show="$wire.show"
    x-cloak
    style="display: none"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    wire:click.self="close"
    x-on:keydown.escape.window="if ($wire.show) $wire.close()"
>
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New guest') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Quick add — you can fill in ID details later from the Guests page.') }}</flux:text>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="firstName" :label="__('First name')" required />
                <flux:input wire:model="lastName" :label="__('Last name')" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="phone" :label="__('Phone')" />
                <flux:input wire:model="email" type="email" :label="__('Email')" />
            </div>

            <flux:input wire:model="nationality" :label="__('Nationality')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="close" type="button">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Create & select') }}</flux:button>
            </div>
        </form>
    </div>
</div>
