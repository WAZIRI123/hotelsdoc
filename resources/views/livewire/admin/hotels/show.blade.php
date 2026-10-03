<section class="w-full space-y-6">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.building-office class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ $hotel->name }}</flux:heading>

            @if ($hotel->isSuspended())
                <flux:badge color="red">{{ __('Suspended') }}</flux:badge>
            @else
                <flux:badge color="green">{{ __('Active') }}</flux:badge>
            @endif

            <form method="POST" action="{{ route('hotels.switch', $hotel) }}">
                @csrf
                <button type="submit"
                    class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                    {{ __('Enter Hotel') }}
                </button>
            </form>

            <button type="button" wire:click="openCreateUser"
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Add User') }}
            </button>

            <a href="{{ route('admin.hotels.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Hotels') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            @foreach (['setup' => __('Setup status'), 'details' => __('Hotel details'), 'users' => __('Users'), 'danger' => __('Suspend / delete')] as $anchor => $label)
                @unless ($loop->first)
                    <span class="text-zinc-400 dark:text-zinc-600">|</span>
                @endunless
                <a href="#{{ $anchor }}" wire:key="nav-{{ $anchor }}"
                    x-on:click.prevent="document.getElementById('{{ $anchor }}').scrollIntoView({behavior: 'smooth', block: 'start'})"
                    class="text-sky-600 hover:underline dark:text-sky-400">{{ $label }}</a>
            @endforeach
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div id="setup" class="space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <flux:heading size="lg">{{ __('Setup status') }}</flux:heading>
                <flux:spacer />
                <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="resyncDefaults">
                    {{ __('Sync default roles & payment methods') }}
                </flux:button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->setupChecklist as $item)
                    <div wire:key="setup-{{ $loop->index }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex items-center gap-2">
                            @if ($item['count'] > 0)
                                <flux:icon.check-circle variant="solid" class="size-5 text-green-600" />
                            @else
                                <flux:icon.exclamation-circle variant="solid" class="size-5 text-amber-500" />
                            @endif
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item['label'] }}</span>
                            <span class="ms-auto text-lg tabular-nums">{{ $item['count'] }}</span>
                        </div>
                        <flux:text class="mt-1 text-xs">{{ $item['hint'] }}</flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div id="details" class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('Hotel details') }}</flux:heading>

        <form wire:submit="saveDetails" class="mt-4 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Hotel name')" required />
                <flux:input wire:model="slug" :label="__('Slug')" required />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="currency" :label="__('Currency')" :disabled="$this->currencyIsLocked"
                    :description:trailing="$this->currencyIsLocked ? __('Locked: the hotel already has sales or expenses in this currency.') : null">
                    @foreach (\App\Livewire\Admin\Hotels\Create::CURRENCIES as $code)
                        <flux:select.option value="{{ $code }}">{{ $code }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="timezone" :label="__('Timezone')" :disabled="$this->timezoneIsLocked"
                    :description:trailing="$this->timezoneIsLocked ? __('Locked: the hotel already has bookings, sales or stock recorded on this clock.') : null">
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

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="check">{{ __('Save details') }}</flux:button>
            </div>
        </form>
    </div>

    <div id="users" class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center gap-3">
            <flux:heading size="lg">{{ __('Users') }}</flux:heading>
            <flux:spacer />
            <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateUser">{{ __('Add user') }}</flux:button>
        </div>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>
                            @php($roleName = $user->roles->first()?->name)
                            @if ($roleName)
                                <flux:badge :color="$roleName === 'admin' ? 'green' : 'zinc'" size="sm">{{ str_replace('_', ' ', $roleName) }}</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">{{ __('No role') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="openEditUser({{ $user->id }})">
                                        {{ __('Edit / reset password') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="user-minus" variant="danger"
                                        wire:click="removeUser({{ $user->id }})"
                                        wire:confirm="{{ __('Remove this user from the hotel?') }}">
                                        {{ __('Remove') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            {{ __('No users yet — add an admin so someone can sign in.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div id="danger" class="rounded-lg border border-red-200 bg-white p-6 shadow-sm dark:border-red-900/60 dark:bg-zinc-900">
        <flux:heading size="lg" class="!text-red-700 dark:!text-red-400">{{ __('Suspend / delete') }}</flux:heading>

        <div class="mt-4 flex flex-wrap items-start gap-4">
            <div class="min-w-64 flex-1">
                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                    {{ $hotel->isSuspended() ? __('Reactivate hotel') : __('Suspend hotel') }}
                </div>
                <flux:text class="mt-1">
                    @if ($hotel->isSuspended())
                        {{ __('Suspended on :date. Staff cannot sign in to it; all data is kept.', ['date' => $hotel->suspended_at->format('d M Y H:i')]) }}
                    @else
                        {{ __('Staff will be blocked from signing in to this hotel. No data is removed and you can reactivate it at any time.') }}
                    @endif
                </flux:text>
            </div>

            @if ($hotel->isSuspended())
                <flux:button icon="play" wire:click="reactivate">{{ __('Reactivate') }}</flux:button>
            @else
                <flux:button variant="danger" icon="pause" wire:click="suspend"
                    wire:confirm="{{ __('Suspend this hotel? Its staff will be signed out of it.') }}">
                    {{ __('Suspend') }}
                </flux:button>
            @endif
        </div>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-64 flex-1">
                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Delete hotel permanently') }}</div>
                <flux:text class="mt-1">
                    {{ __('Removes the hotel and ALL its rooms, guests, reservations, folios, payments and POS data. This cannot be undone. User accounts are kept.') }}
                </flux:text>
                @unless ($hotel->isSuspended())
                    <flux:text class="mt-1 !text-amber-600">{{ __('Suspend the hotel first to enable deletion.') }}</flux:text>
                @endunless
            </div>

            <flux:modal.trigger name="delete-hotel">
                <flux:button variant="danger" icon="trash" :disabled="! $hotel->isSuspended()">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <flux:modal name="delete-hotel" class="md:w-[480px]">
        <form wire:submit="deleteHotel" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete :name?', ['name' => $hotel->name]) }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('All of this hotel\'s data will be permanently deleted. Type the slug below to confirm.') }}
                </flux:text>
            </div>

            <flux:input wire:model="deleteConfirmation" :label="__('Type :slug to confirm', ['slug' => $hotel->slug])" autocomplete="off" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" icon="trash">{{ __('Delete permanently') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="hotel-user-form" class="md:w-[480px]">
        <form wire:submit="saveUser" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingUserId ? __('Edit user') : __('Add user') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $editingUserId
                        ? __('Leave the password blank to keep the current one.')
                        : __('If the email already exists, that user is added to this hotel and their password is unchanged.') }}
                </flux:text>
            </div>

            <flux:input wire:model="userName" :label="__('Name')" required />
            <flux:input wire:model="userEmail" :label="__('Email')" type="email" required />
            <flux:input wire:model="userPassword" :label="$editingUserId ? __('New password') : __('Password')" type="password" viewable />

            <flux:select wire:model="userRole" :label="__('Role')">
                @foreach ($this->availableRoles as $roleOption)
                    <flux:select.option value="{{ $roleOption }}">{{ str_replace('_', ' ', $roleOption) }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
