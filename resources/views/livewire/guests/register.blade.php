<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.user-plus class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ $guest ? __('Edit Guest') : __('Register Guest') }}</flux:heading>

            <flux:spacer />

            <a href="{{ route('guests.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Guest Book') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('guests.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Guest Book') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('guests.list') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('All Guests') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('reservations.create') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('New Reservation') }}</a>
        </nav>

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
                <flux:select wire:model="gender" :label="__('Gender')" :placeholder="__('Select gender…')">
                    <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                    <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model="phone" :label="__('Phone')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="email" type="email" :label="__('Email')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="nationality" :label="__('Nationality')" :placeholder="__('Select country…')">
                    @foreach ($countries as $country)
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
                <flux:button variant="ghost" :href="route('guests.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" icon="check">
                    {{ $guest ? __('Save Changes') : __('Register Guest') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
