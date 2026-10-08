@php
    $th = 'border border-zinc-200 px-4 py-3 text-center font-semibold text-zinc-800 dark:border-zinc-700 dark:text-zinc-100';
    $td = 'border border-zinc-200 bg-zinc-50 px-4 py-3 text-center text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-200';
@endphp

<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.information-circle class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('System Control') }}</flux:heading>

            <flux:spacer />

            <a href="{{ route('admin.hotels.create') }}" wire:navigate data-test="add-organization"
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Add Organization') }}
            </a>
            <a href="{{ route('admin.organization') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Dashboard') }}
            </a>
        </div>

        @if ($this->hotels->count() > 1)
            <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                @foreach ($this->hotels as $hotel)
                    @unless ($loop->first)
                        <span class="text-zinc-400 dark:text-zinc-600">|</span>
                    @endunless
                    <a href="#organization-{{ $hotel->id }}" wire:key="nav-{{ $hotel->id }}"
                        x-on:click.prevent="document.getElementById('organization-{{ $hotel->id }}').scrollIntoView({behavior: 'smooth', block: 'start'})"
                        class="text-sky-600 hover:underline dark:text-sky-400">{{ $hotel->name }}</a>
                @endforeach
            </nav>
        @endif

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="space-y-10">
            @forelse ($this->hotels as $hotel)
                <div id="organization-{{ $hotel->id }}" wire:key="organization-{{ $hotel->id }}" class="scroll-mt-6 space-y-6 overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th class="{{ $th }}">{{ __('Logo') }}</th>
                                <th class="{{ $th }}">{{ __('Approved-by') }}</th>
                                <th class="{{ $th }}">{{ __('Edited') }}</th>
                                <th class="{{ $th }}">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="{{ $td }} w-1/6">
                                    @if ($hotel->logoUrl())
                                        <img src="{{ $hotel->logoUrl() }}" alt="{{ $hotel->name }}" class="mx-auto max-h-28 w-auto" />
                                    @else
                                        <flux:icon.building-office class="mx-auto size-16 text-zinc-300 dark:text-zinc-600" />
                                    @endif
                                </td>
                                <td class="{{ $td }} align-top">
                                    {{ $hotel->approver?->name }} / {{ $hotel->created_at?->format('Y-m-d') }}
                                </td>
                                <td class="{{ $td }} align-top">
                                    {{ $hotel->editor?->name }} / {{ $hotel->edited_by ? $hotel->updated_at?->format('Y-m-d') : '' }}
                                </td>
                                <td class="{{ $td }}">
                                    <div class="flex flex-col items-center gap-4">
                                        @if ($hotel->isSuspended())
                                            <button type="button" wire:click="restoreAccess({{ $hotel->id }})"
                                                class="rounded-md bg-green-600 px-5 py-2 text-base text-white shadow-sm transition hover:bg-green-700">
                                                {{ __('Restore System Access') }}
                                            </button>
                                        @else
                                            <button type="button" wire:click="blockAccess({{ $hotel->id }})"
                                                wire:confirm="{{ __('Block system access for :name? Its staff will be signed out of it.', ['name' => $hotel->name]) }}"
                                                class="rounded-md bg-amber-500 px-5 py-2 text-base text-white shadow-sm transition hover:bg-amber-600">
                                                {{ __('Block System Access') }}
                                            </button>
                                        @endif
                                        <button type="button" wire:click="confirmDelete({{ $hotel->id }})"
                                            class="rounded-md bg-red-600 px-5 py-2 text-base text-white shadow-sm transition hover:bg-red-700">
                                            {{ __('Delete All Operations') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th class="{{ $th }} w-1/3">{{ __('Organization') }}</th>
                                <th class="{{ $th }} w-1/3">{{ __('Tax Identification Number') }}</th>
                                <th class="{{ $th }} w-1/3">{{ __('Address') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="{{ $td }}">
                                    <a href="{{ route('admin.hotels.show', $hotel) }}" wire:navigate class="text-sky-600 hover:underline dark:text-sky-400">{{ $hotel->name }}</a>
                                    @if ($hotel->isSuspended())
                                        <flux:badge size="sm" color="amber" class="ms-1">{{ __('Blocked') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="{{ $td }}">{{ $hotel->tin }}</td>
                                <td class="{{ $td }}">{{ $hotel->address }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th class="{{ $th }} w-1/3">{{ __('Phone') }}</th>
                                <th class="{{ $th }} w-1/3">{{ __('Email') }}</th>
                                <th class="{{ $th }} w-1/3">{{ __('Fax') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="{{ $td }}">{{ $hotel->phone }}</td>
                                <td class="{{ $td }}">{{ $hotel->email }}</td>
                                <td class="{{ $td }}">{{ $hotel->fax }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th class="{{ $th }}">{{ __('Street') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="{{ $td }}">{{ $hotel->street }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @unless ($loop->last)
                    <hr class="border-zinc-300 dark:border-zinc-700" />
                @endunless
            @empty
                <flux:text>{{ __('No organizations yet.') }}</flux:text>
            @endforelse
        </div>
    </div>

    <flux:modal name="delete-operations" class="md:w-[480px]">
        @if ($this->deletingHotel)
            <form wire:submit="deleteOperations" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete all operations of :name?', ['name' => $this->deletingHotel->name]) }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('This permanently deletes the hotel and ALL its rooms, guests, reservations, guest billing, payments and POS data. User accounts are kept. This cannot be undone.') }}
                    </flux:text>
                    @unless ($this->deletingHotel->isSuspended())
                        <flux:text class="mt-2 !text-amber-600">{{ __('Block system access first to enable deletion.') }}</flux:text>
                    @endunless
                </div>

                <flux:input wire:model="deleteConfirmation" :label="__('Type :slug to confirm', ['slug' => $this->deletingHotel->slug])" autocomplete="off" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger" icon="trash" :disabled="! $this->deletingHotel->isSuspended()">{{ __('Delete permanently') }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</section>
