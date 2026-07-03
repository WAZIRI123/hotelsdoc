<section class="w-full">
    <div class="flex items-center justify-between gap-4 pb-6">
        <div>
            <flux:heading size="xl">{{ __('Rooms') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Individual rooms in your property.') }}</flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:select wire:model.live="filterStatus" size="sm">
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
                <flux:select.option value="available">{{ __('Available') }}</flux:select.option>
                <flux:select.option value="occupied">{{ __('Occupied') }}</flux:select.option>
                <flux:select.option value="cleaning">{{ __('Cleaning') }}</flux:select.option>
                <flux:select.option value="maintenance">{{ __('Maintenance') }}</flux:select.option>
                <flux:select.option value="out_of_order">{{ __('Out of order') }}</flux:select.option>
            </flux:select>

            <flux:button variant="primary" icon="plus" wire:click="openCreate"
                :disabled="$this->roomTypes->isEmpty()">
                {{ __('New room') }}
            </flux:button>
        </div>
    </div>

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

            <flux:select wire:model="status" :label="__('Status')" required>
                <flux:select.option value="available">{{ __('Available') }}</flux:select.option>
                <flux:select.option value="occupied">{{ __('Occupied') }}</flux:select.option>
                <flux:select.option value="cleaning">{{ __('Cleaning') }}</flux:select.option>
                <flux:select.option value="maintenance">{{ __('Maintenance') }}</flux:select.option>
                <flux:select.option value="out_of_order">{{ __('Out of order') }}</flux:select.option>
            </flux:select>

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
