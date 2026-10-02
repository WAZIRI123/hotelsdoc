<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.building-office-2 class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Rooms') }}</flux:heading>

            <button type="button" wire:click="openCreate" @disabled($this->roomTypes->isEmpty())
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-offset-zinc-900">
                {{ __('New room') }}
            </button>

            <a href="{{ route('room-types.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Room types') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="#" wire:click.prevent="$set('filterStatus', 'all')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('All') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" wire:click.prevent="$set('filterStatus', 'available')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Available') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" wire:click.prevent="$set('filterStatus', 'occupied')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Occupied') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" wire:click.prevent="$set('filterStatus', 'cleaning')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Cleaning') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" wire:click.prevent="$set('filterStatus', 'maintenance')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Maintenance') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" wire:click.prevent="$set('filterStatus', 'out_of_order')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Out of order') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

    @if ($this->roomTypes->isEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Add a room type first') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Every room belongs to a room type (Standard, Deluxe, etc.).') }}
                <flux:link :href="route('room-types.index')" wire:navigate>{{ __('Manage room types') }}</flux:link>
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Number') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->rooms as $room)
                <flux:table.row :key="$room->id">
                    <flux:table.cell variant="strong">{{ $room->number }}</flux:table.cell>
                    <flux:table.cell>{{ $room->roomType?->name }}</flux:table.cell>
                    <flux:table.cell>
                        @php
                            $color = match ($room->status) {
                                'available' => 'green',
                                'occupied' => 'blue',
                                'cleaning' => 'yellow',
                                'maintenance' => 'orange',
                                'out_of_order' => 'red',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge :color="$color" size="sm">
                            {{ str_replace('_', ' ', ucfirst($room->status)) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                @if ($room->status === 'cleaning')
                                    <flux:menu.item icon="sparkles" wire:click="markClean({{ $room->id }})">
                                        {{ __('Mark clean') }}
                                    </flux:menu.item>
                                @endif
                                <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $room->id }})">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger"
                                    wire:click="delete({{ $room->id }})"
                                    wire:confirm="{{ __('Delete this room?') }}">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">
                        {{ __('No rooms found.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    </div>

    <flux:modal name="room-form" class="md:w-[480px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? __('Edit room') : __('New room') }}
                </flux:heading>
            </div>

            <flux:select wire:model="room_type_id" :label="__('Room type')" required>
                <flux:select.option value="">{{ __('Choose a type…') }}</flux:select.option>
                @foreach ($this->roomTypes as $type)
                    <flux:select.option :value="$type->id">{{ $type->name }} ({{ $type->code }})</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="number" :label="__('Room number')" required autofocus />

            @if ($isOccupied)
                <flux:select :label="__('Status')" disabled
                    :description:trailing="__('Guests are in this room. The status changes at check-out or when they move rooms.')">
                    <flux:select.option value="occupied" selected>{{ __('Occupied') }}</flux:select.option>
                </flux:select>
            @else
                <flux:select wire:model="status" :label="__('Status')" required
                    :description:trailing="__('Maintenance and Out of order take the room off sale until it is set back to Available.')">
                    <flux:select.option value="available">{{ __('Available') }}</flux:select.option>
                    <flux:select.option value="cleaning">{{ __('Cleaning') }}</flux:select.option>
                    <flux:select.option value="maintenance">{{ __('Maintenance') }}</flux:select.option>
                    <flux:select.option value="out_of_order">{{ __('Out of order') }}</flux:select.option>
                </flux:select>
            @endif

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />
            <flux:switch wire:model="is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
