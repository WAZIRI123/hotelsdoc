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
                <flux:field>
                    <flux:label>{{ __('Adults') }}</flux:label>
                    <input
                        wire:model="adults"
                        type="number"
                        min="1"
                        max="{{ $this->selectedRoomType?->max_adults }}"
                        required
                        class="block h-10 w-full rounded-lg border bg-white px-3 py-2 text-base shadow-xs dark:bg-white/10 sm:text-sm"
                    />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Children') }}</flux:label>
                    <input
                        wire:model="children"
                        type="number"
                        min="0"
                        max="{{ $this->selectedRoomType?->max_children }}"
                        class="block h-10 w-full rounded-lg border bg-white px-3 py-2 text-base shadow-xs dark:bg-white/10 sm:text-sm"
                    />
                </flux:field>
            </div>

            @if ($this->selectedRoomType)
                <flux:text size="sm" class="text-zinc-500">
                    {{ __('Sleeps up to :max guests (:adults adults + :children children). Base rate covers :base guest(s).', [
                        'max' => $this->selectedRoomType->max_occupancy,
                        'adults' => $this->selectedRoomType->max_adults,
                        'children' => $this->selectedRoomType->max_children,
                        'base' => $this->selectedRoomType->base_occupancy,
                    ]) }}
                    @if ($this->selectedRoomType->extra_guest_fee->isPositive())
                        {{ __('Extra guest: :fee/night.', ['fee' => $this->selectedRoomType->extra_guest_fee->format()]) }}
                    @endif
                </flux:text>
            @endif
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

            @if ($room_type_id)
                @if ($this->availableRooms->isEmpty())
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        <flux:callout.heading>{{ __('No rooms available') }}</flux:callout.heading>
                        <flux:callout.text>{{ __('Pick a different type or change the dates.') }}</flux:callout.text>
                    </flux:callout>
                @else
                    <flux:select wire:model.live="room_id" :label="__('Assign room')" required>
                        <flux:select.option value="">{{ __('Choose a room…') }}</flux:select.option>
                        @foreach ($this->availableRooms as $room)
                            <flux:select.option :value="$room->id">
                                {{ __('Room') }} {{ $room->number }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            @endif

            @if ($room_type_id && $this->availableRatePlans->isNotEmpty())
                <flux:select wire:model.live="rate_plan_id" :label="__('Rate plan')">
                    <flux:select.option value="">{{ __('Default rate (room only)') }}</flux:select.option>
                    @foreach ($this->availableRatePlans as $plan)
                        <flux:select.option :value="$plan->id">
                            {{ $plan->name }}
                            @if ($plan->mealPlan) — {{ $plan->mealPlan->code }} @endif
                            — {{ $plan->price->format() }}/night
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if ($this->selectedRatePlan?->mealPlan)
                <div class="flex flex-wrap items-center gap-2">
                    <flux:text size="sm" class="text-zinc-500">{{ __('Meals included:') }}</flux:text>
                    @if ($this->selectedRatePlan->mealPlan->mealsIncludedCount() === 0)
                        <flux:badge color="zinc" size="sm">{{ __('Room only') }}</flux:badge>
                    @else
                        @if ($this->selectedRatePlan->mealPlan->includes_breakfast)
                            <flux:badge color="amber" size="sm">{{ __('Breakfast') }}</flux:badge>
                        @endif
                        @if ($this->selectedRatePlan->mealPlan->includes_lunch)
                            <flux:badge color="orange" size="sm">{{ __('Lunch') }}</flux:badge>
                        @endif
                        @if ($this->selectedRatePlan->mealPlan->includes_dinner)
                            <flux:badge color="rose" size="sm">{{ __('Dinner') }}</flux:badge>
                        @endif
                    @endif
                </div>
            @endif

            @if ($this->previewNightlyRate)
                <flux:callout icon="banknotes">
                    <flux:callout.heading>
                        {{ __('Nightly rate: :rate', ['rate' => $this->previewNightlyRate->format()]) }}
                    </flux:callout.heading>
                    @if ($this->selectedRatePlan)
                        <flux:callout.text>
                            {{ __('Base: :base', ['base' => $this->selectedRatePlan->price->format()]) }}
                            @php $extraAdults = max(0, $adults - ($this->selectedRoomType?->base_occupancy ?? 0)); @endphp
                            @if ($extraAdults > 0 && $this->selectedRatePlan->extra_adult_price->isPositive())
                                · {{ __(':n extra adult(s) × :fee', ['n' => $extraAdults, 'fee' => $this->selectedRatePlan->extra_adult_price->format()]) }}
                            @endif
                            @if ($children > 0 && $this->selectedRatePlan->extra_child_price->isPositive())
                                · {{ __(':n child(ren) × :fee', ['n' => $children, 'fee' => $this->selectedRatePlan->extra_child_price->format()]) }}
                            @endif
                        </flux:callout.text>
                    @endif
                </flux:callout>
            @endif

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('reservations.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Create reservation') }}</flux:button>
        </div>
    </form>

    <livewire:reservations.new-guest-form />
</section>
