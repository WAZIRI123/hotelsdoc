<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.building-office class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('New Hotel') }}</flux:heading>

            <flux:spacer />

            <a href="{{ route('admin.hotels.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Hotels') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="#hotel-details"
                x-on:click.prevent="document.getElementById('hotel-details').scrollIntoView({behavior: 'smooth', block: 'start'})"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('1. Hotel details') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#hotel-admin"
                x-on:click.prevent="document.getElementById('hotel-admin').scrollIntoView({behavior: 'smooth', block: 'start'})"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('2. Hotel administrator') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <form wire:submit="save" class="space-y-8">
            <div id="hotel-details" class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Hotel details') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Default roles and payment methods are created automatically.') }}</flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model.live.blur="name" :label="__('Hotel name')" required autofocus />
                    <flux:input wire:model.blur="slug" :label="__('Slug')" :description="__('Unique short identifier, e.g. serengeti-view.')" required />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="currency" :label="__('Currency')">
                        @foreach (\App\Livewire\Admin\Hotels\Create::CURRENCIES as $code)
                            <flux:select.option value="{{ $code }}">{{ $code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="timezone" :label="__('Timezone')">
                        @foreach ($this->timezones as $zone)
                            <flux:select.option value="{{ $zone }}">{{ $zone }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="vat_mode" :label="__('VAT')">
                        @foreach (\App\Support\Vat::MODES as $mode => $label)
                            <flux:select.option value="{{ $mode }}">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($vat_mode !== \App\Support\Vat::NONE)
                        <flux:input wire:model="vat_rate" type="number" min="0" max="100" step="0.01" :label="__('VAT rate (%)')" required
                            :description:trailing="$vat_mode === \App\Support\Vat::INCLUSIVE ? __('Prices entered for rooms, menu and extras already include VAT.') : __('VAT is added on top of the prices entered for rooms, menu and extras.')" />
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="phone" :label="__('Phone')" />
                    <flux:input wire:model="email" type="email" :label="__('Email')" />
                    <flux:input wire:model="fax" :label="__('Fax')" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="address" :label="__('Address')" />
                    <flux:input wire:model="street" :label="__('Street')" />
                </div>

                <div class="flex flex-wrap items-end gap-4">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="" class="h-16 w-auto rounded border border-zinc-200 bg-white p-1 dark:border-zinc-700" />
                    @elseif (isset($hotel) && $hotel->logoUrl())
                        <img src="{{ $hotel->logoUrl() }}" alt="{{ $hotel->name }}" class="h-16 w-auto rounded border border-zinc-200 bg-white p-1 dark:border-zinc-700" />
                    @endif
                    <div class="min-w-64 flex-1">
                        <flux:input type="file" wire:model="logo" accept="image/*" :label="__('Logo')" :description="__('PNG or JPG, up to 2 MB.')" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="tin" :label="__('TIN')" />
                    <flux:input wire:model="vrn" :label="__('VRN')" />
                </div>
            </div>

            <hr class="border-zinc-200 dark:border-zinc-700" />

            <div id="hotel-admin" class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Hotel administrator') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('This person signs in to the hotel with the admin role and finishes the setup (rooms, rates, staff).') }}</flux:text>
                </div>

                <flux:input wire:model.live.blur="adminEmail" type="email" :label="__('Admin email')" required />

                @if ($this->adminExists)
                    <flux:callout icon="information-circle" color="sky">
                        <flux:callout.text>{{ __('A user with this email already exists. They will be added to this hotel as admin; their password is unchanged.') }}</flux:callout.text>
                    </flux:callout>
                @else
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="adminName" :label="__('Admin name')" required />
                        <flux:input wire:model="adminPassword" type="password" :label="__('Password')" viewable required />
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('admin.hotels.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Create Hotel') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
