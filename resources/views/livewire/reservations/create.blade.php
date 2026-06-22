<section class="w-full max-w-3xl">
    <div class="pb-6">
        <flux:heading size="xl">{{ __('New reservation') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Pick a guest, dates, room type, and room.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Guest') }}</flux:heading>

            <livewire:reservations.guest-picker />

            @error('guest_id') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Stay') }}</flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model.live="arrival" type="date" :label="__('Arrival')" required />
                <flux:input wire:model.live="departure" type="date" :label="__('Departure')" required />
            </div>

            <div class="grid grid-cols-3 gap-4">
                <flux:input wire:model.number="adults" type="number" min="1" max="10" :label="__('Adults')" required />
                <flux:input wire:model.number="children" type="number" min="0" max="10" :label="__('Children')" />
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Room') }}</flux:heading>

            <flux:select wire:model.live="room_type_id" :label="__('Room type')" required>
                <flux:select.option value="">{{ __('Choose a type…') }}</flux:select.option>
                @foreach ($this->roomTypes as $type)
                    <flux:select.option :value="$type->id">
                        {{ $type->name }} — {{ $type->default_rate->format() }}/night
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div>
                @if ($room_type_id)
                    @if ($this->availableRooms->isEmpty())
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.heading>{{ __('No rooms available') }}</flux:callout.heading>
                            <flux:callout.text>{{ __('Pick a different type or change the dates.') }}</flux:callout.text>
                        </flux:callout>
                    @else
                        <flux:select wire:model="room_id" :label="__('Assign room')" required>
                            <flux:select.option value="">{{ __('Choose a room…') }}</flux:select.option>
                            @foreach ($this->availableRooms as $room)
                                <flux:select.option :value="$room->id">
                                    {{ __('Room') }} {{ $room->number }} @if ($room->floor) — {{ __('Floor') }} {{ $room->floor }} @endif
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                @endif
            </div>

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('reservations.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Create reservation') }}</flux:button>
        </div>
    </form>

    <livewire:reservations.new-guest-form />
</section>
