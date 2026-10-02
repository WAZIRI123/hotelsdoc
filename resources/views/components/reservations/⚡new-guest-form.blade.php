<?php

use App\Models\Guest;
use App\Support\Countries;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public bool $show = false;

    #[Validate('required|string|max:160')]
    public string $guest_names = '';

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

    #[On('new-guest-form:open')]
    public function open(string $prefillFirstName = ''): void
    {
        $this->resetForm();
        $this->guest_names = $prefillFirstName;
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

        [$firstName, $lastName] = array_pad(preg_split('/\s+/', trim($this->guest_names), 2), 2, null);

        $guest = Guest::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'caller_name' => $this->caller_name ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'nationality' => $this->nationality ?: null,
            'id_type' => $this->id_type ?: null,
            'id_number' => $this->id_type !== '' && $this->id_number !== '' ? $this->id_number : null,
            'address' => $this->address ?: null,
        ]);

        $this->resetForm();
        $this->show = false;

        $this->dispatch('guest-created', id: $guest->id);
        Flux::toast(variant: 'success', text: 'Guest '.$guest->fullName().' registered and selected.');
    }

    private function resetForm(): void
    {
        $this->reset(['guest_names', 'caller_name', 'phone', 'email', 'nationality', 'id_type', 'id_number', 'address']);
        $this->resetValidation();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function countries(): array
    {
        return Countries::all();
    }
};
?>

<div
    x-data
    x-show="$wire.show"
    x-cloak
    style="display: none"
    class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4"
    wire:click.self="close"
    x-on:keydown.escape.window="if ($wire.show) $wire.close()"
>
    <div class="w-full max-w-2xl rounded-lg border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.user-plus class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Register Guest') }}</flux:heading>

            <flux:spacer />

            <flux:button variant="ghost" icon="x-mark" size="sm" wire:click="close" type="button" :aria-label="__('Close')" />
        </div>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Guest names') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="guest_names" required />
                    <flux:error name="guest_names" />
                </flux:field>
                <flux:input wire:model="caller_name" :label="__('Caller name')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="phone" :label="__('Phone')" />
                <flux:input wire:model="email" type="email" :label="__('Email')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="nationality" :label="__('Nationality')" :placeholder="__('Select country…')">
                    @foreach ($this->countries as $country)
                        <flux:select.option :value="$country">{{ $country }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="id_type" :label="__('ID type')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    <flux:select.option value="passport">{{ __('Passport') }}</flux:select.option>
                    <flux:select.option value="national_id">{{ __('National ID') }}</flux:select.option>
                    <flux:select.option value="drivers_license">{{ __('Driver’s license') }}</flux:select.option>
                </flux:select>
            </div>

            @if ($id_type !== '')
                <flux:input wire:model="id_number" :label="__('ID number')" />
            @endif

            <flux:textarea wire:model="address" :label="__('Address')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="close" type="button">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="check">{{ __('Register & select') }}</flux:button>
            </div>
        </form>
    </div>
</div>
